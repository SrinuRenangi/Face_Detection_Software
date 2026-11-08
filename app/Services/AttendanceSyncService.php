<?php

namespace App\Services;

use App\Models\AttendanceRecordModel;
use App\Models\AttendanceEventModel;
use App\Models\AttendancePhotoModel;
use App\Models\AdminAuditLogModel;
use Config\Database;

class AttendanceSyncService
{
    protected AttendanceRecordModel $attendanceModel;
    protected AttendanceEventModel $eventModel;
    protected AttendancePhotoModel $photoModel;
    protected AdminAuditLogModel $auditModel;
    protected $db;

    public function __construct()
    {
        $this->attendanceModel = new AttendanceRecordModel();
        $this->eventModel      = new AttendanceEventModel();
        $this->photoModel      = new AttendancePhotoModel();
        $this->auditModel      = new AdminAuditLogModel();
        $this->db              = Database::connect();
    }

    /**
     * Process an incoming attendance punch from any registered device (phone or tablet).
     * Ingests into append-only attendance_events ledger and updates the daily attendance summary.
     * Enforces terminal modes (ENTRY_ONLY, EXIT_ONLY), concurrency locking, and clock trust.
     *
     * @param array $punchData [
     *     'client_uuid'            => string,
     *     'employee_id'            => string (UUID),
     *     'device_id'              => string (UUID),
     *     'timestamp'              => string ('Y-m-d H:i:s' or ISO-8601),
     *     'client_synced_at'       => ?string,
     *     'confidence_score'       => float,
     *     'sync_source'            => 'DIRECT_ONLINE' | 'OFFLINE_SYNC',
     *     'device_epoch_id'        => ?string,
     *     'device_event_sequence'  => ?int,
     *     'client_timezone'        => ?string,
     *     'client_monotonic_ms'    => ?int,
     *     'terminal_mode'          => ?string ('ENTRY_ONLY' | 'EXIT_ONLY' | 'DIRECTIONAL_AUTO'),
     *     'movement_direction'     => ?string ('ENTRY' | 'EXIT' | 'UNKNOWN')
     * ]
     * @return array
     */
    public function processPunch(array $punchData): array
    {
        $clientUuid        = $punchData['client_uuid'] ?? self::generateUuidV4();
        $employeeId        = $punchData['employee_id'];
        $deviceId          = $punchData['device_id'];
        $punchTimestamp    = $punchData['timestamp'];
        $confidenceScore   = (float) ($punchData['confidence_score'] ?? 0.85);
        $syncSource        = $punchData['sync_source'] ?? 'DIRECT_ONLINE';
        $clientTimezone    = $punchData['client_timezone'] ?? 'Asia/Kolkata';
        $clientMonotonicMs = isset($punchData['client_monotonic_ms']) ? (int) $punchData['client_monotonic_ms'] : null;
        $photoMetadata     = $punchData['photo_metadata'] ?? null;

        $schoolTz = new \DateTimeZone('Asia/Kolkata');
        $utcTz    = new \DateTimeZone('UTC');

        // 1. Timezone Parsing and Work-Date Normalization
        try {
            $parsedDate = new \DateTimeImmutable($punchTimestamp, $schoolTz);
        } catch (\Exception $e) {
            return [
                'status' => 'INVALID_TIMESTAMP_FORMAT',
                'error'  => 'Timestamp format is invalid. RFC3339/ISO-8601 expected.',
            ];
        }

        // Canonical UTC format for storage in attendance_events (client_recorded_at)
        $clientRecordedUtc   = $parsedDate->setTimezone($utcTz);
        $clientRecordedAtStr = $clientRecordedUtc->format('Y-m-d H:i:s');
        $punchEpochTime      = $clientRecordedUtc->getTimestamp();

        // Convert to school local timezone (Asia/Kolkata) to derive official work_date and local summary time
        $clientRecordedIst   = $parsedDate->setTimezone($schoolTz);
        $workDate            = $clientRecordedIst->format('Y-m-d');
        $localTimeStr        = $clientRecordedIst->format('Y-m-d H:i:s');

        // 2. Client Synced At & Server Received At Telemetry
        $serverReceivedUtc   = new \DateTimeImmutable('now', $utcTz);
        $serverReceivedAt    = $serverReceivedUtc->format('Y-m-d H:i:s');
        $serverEpochTime     = $serverReceivedUtc->getTimestamp();

        $clientSyncedTimestamp = $punchData['client_synced_at'] ?? null;
        if (!empty($clientSyncedTimestamp)) {
            try {
                $clientSyncedUtc   = (new \DateTimeImmutable($clientSyncedTimestamp, $schoolTz))->setTimezone($utcTz);
                $clientSyncedAtStr = $clientSyncedUtc->format('Y-m-d H:i:s');
                $clientSyncedEpoch = $clientSyncedUtc->getTimestamp();
            } catch (\Exception $e) {
                $clientSyncedAtStr = null;
                $clientSyncedEpoch = null;
            }
        } else {
            // For direct online punches or default offline sync, sync initiation is contemporaneous with arrival
            $clientSyncedAtStr = ($syncSource === 'DIRECT_ONLINE') ? $clientRecordedAtStr : $serverReceivedAt;
            $clientSyncedEpoch = ($syncSource === 'DIRECT_ONLINE') ? $punchEpochTime : $serverEpochTime;
        }

        // Queue duration and real clock offset calculations
        $queueDurationSeconds = ($clientSyncedEpoch !== null) ? ($clientSyncedEpoch - $punchEpochTime) : 0;
        $clockOffsetSeconds   = ($clientSyncedEpoch !== null) ? ($clientSyncedEpoch - $serverEpochTime) : 0;

        // 3. Canonical Payload Hash for Idempotency
        $canonicalPayload = [
            'client_uuid' => $clientUuid,
            'employee_id' => $employeeId,
            'device_id'   => $deviceId,
            'timestamp'   => $clientRecordedAtStr,
        ];
        $payloadHash = hash('sha256', json_encode($canonicalPayload));

        // 4. Idempotency Check by client_uuid against attendance_events
        $existingEvent = $this->eventModel->where('client_uuid', $clientUuid)->first();
        if ($existingEvent) {
            if ($existingEvent['payload_hash'] === $payloadHash) {
                // Fetch summary for event date in school timezone
                $existingIstDate = (new \DateTimeImmutable($existingEvent['client_recorded_at'], $utcTz))
                    ->setTimezone($schoolTz)
                    ->format('Y-m-d');

                $summary = $this->attendanceModel
                    ->where('employee_id', $employeeId)
                    ->where('work_date', $existingIstDate)
                    ->first();

                $existingPhoto = $this->photoModel->findByEventId($existingEvent['id']);

                return [
                    'status'         => 'IDEMPOTENT_CONFIRMED',
                    'event_id'       => $existingEvent['id'],
                    'record_id'      => $summary['id'] ?? null,
                    'check_in_time'  => $summary['check_in_time'] ?? $localTimeStr,
                    'check_out_time' => $summary['check_out_time'] ?? null,
                    'photo_id'       => $existingPhoto['id'] ?? null,
                    'photo_is_retry' => ($existingPhoto !== null),
                    'message'        => 'Punch already recorded with identical client UUID.',
                ];
            } else {
                $this->auditModel->log(
                    'SYSTEM_SECURITY',
                    'CLIENT_UUID_PAYLOAD_MISMATCH',
                    json_encode([
                        'client_uuid'   => $clientUuid,
                        'device_id'     => $deviceId,
                        'employee_id'   => $employeeId,
                        'recorded_hash' => $existingEvent['payload_hash'],
                        'incoming_hash' => $payloadHash,
                    ]),
                    'ATTENDANCE_EVENT',
                    $existingEvent['id']
                );

                return [
                    'status' => 'CONFLICT_PAYLOAD_MISMATCH',
                    'error'  => 'Duplicate client UUID submitted with conflicting payload content.',
                ];
            }
        }

        // 5. Query Authoritative Device Settings from Database
        $deviceRow = $this->db->table('kiosk_devices')->where('id', $deviceId)->get()->getRowArray();
        if (!$deviceRow) {
            return [
                'status' => 'DEVICE_UNAUTHORIZED',
                'error'  => 'Unrecognized or unregistered attendance terminal.',
            ];
        }

        if ($deviceRow['status'] !== 'ACTIVE') {
            return [
                'status' => 'DEVICE_REVOKED',
                'error'  => 'This attendance terminal has been revoked or suspended.',
            ];
        }

        // Server-authoritative settings (Client JSON cannot override)
        $terminalMode = $deviceRow['terminal_mode'] ?? 'DIRECTIONAL_AUTO';

        $deviceEpochId = !empty($deviceRow['device_epoch_id']) ? $deviceRow['device_epoch_id'] : ($punchData['device_epoch_id'] ?? 'epoch-default-1');
        $submittedEpochId = $punchData['submitted_epoch_id'] ?? $punchData['device_epoch_id'] ?? $deviceEpochId;
        $lastSequence  = (int) ($deviceRow['last_event_sequence'] ?? 0);
        $deviceSeq     = isset($punchData['device_event_sequence'])
            ? (int) $punchData['device_event_sequence']
            : ($lastSequence + 1);

        // Check if event was queued under a retired/prior device epoch
        $isRetiredEpoch = false;
        if (!empty($deviceRow['device_epoch_id']) && !empty($submittedEpochId) && $submittedEpochId !== $deviceRow['device_epoch_id']) {
            $isRetiredEpoch = true;
            $this->auditModel->log(
                'SYSTEM_SECURITY',
                'RETIRED_EPOCH_EVENT_HELD',
                json_encode([
                    'client_uuid'         => $clientUuid,
                    'device_id'           => $deviceId,
                    'active_epoch_id'     => $deviceEpochId,
                    'submitted_epoch_id'  => $submittedEpochId,
                    'sequence'            => $deviceSeq,
                ]),
                'ATTENDANCE_EVENT',
                null
            );
        }

        // 6. Monotonic Sequence Conflict, Gap & Progression Check
        $seqConflict = $this->eventModel
            ->where('device_id', $deviceId)
            ->where('device_epoch_id', $submittedEpochId)
            ->where('device_event_sequence', $deviceSeq)
            ->first();

        if ($seqConflict) {
            $this->auditModel->log(
                'SYSTEM_SECURITY',
                'DEVICE_SEQUENCE_COLLISION',
                json_encode([
                    'client_uuid'           => $clientUuid,
                    'employee_id'           => $employeeId,
                    'device_id'             => $deviceId,
                    'device_epoch_id'       => $submittedEpochId,
                    'device_event_sequence' => $deviceSeq,
                    'conflicting_event_id'  => $seqConflict['id'],
                ]),
                'ATTENDANCE_EVENT',
                $seqConflict['id']
            );

            return [
                'status' => 'QUARANTINED',
                'error'  => 'Duplicate sequence number detected from device; event quarantined.',
            ];
        }

        $isOutOfOrder = false;
        if ($deviceSeq < $lastSequence) {
            // Section 7: Out-of-order sequence arrival. Retained in ledger and held for review without altering daily aggregate
            $isOutOfOrder = true;
            $this->auditModel->log(
                'SYSTEM_SECURITY',
                'DEVICE_SEQUENCE_OUT_OF_ORDER',
                json_encode([
                    'client_uuid'         => $clientUuid,
                    'device_id'           => $deviceId,
                    'device_epoch_id'     => $submittedEpochId,
                    'submitted_sequence'  => $deviceSeq,
                    'last_event_sequence' => $lastSequence,
                ]),
                'ATTENDANCE_EVENT',
                null
            );
        } elseif ($deviceSeq > $lastSequence + 1) {
            // Sequence gap observed: audit anomaly
            $this->auditModel->log(
                'SYSTEM_SECURITY',
                'DEVICE_SEQUENCE_GAP',
                json_encode([
                    'client_uuid'        => $clientUuid,
                    'device_id'          => $deviceId,
                    'device_epoch_id'    => $submittedEpochId,
                    'expected_sequence'  => $lastSequence + 1,
                    'submitted_sequence' => $deviceSeq,
                ]),
                'ATTENDANCE_EVENT',
                null
            );
        }

        // 7. Clock Trust & Defensible Offline Server-Time Anchor Evaluation
        $clockTrust = 'NORMAL_SYNC';
        $holdForReview = false;

        $serverAnchorId    = $punchData['server_anchor_id'] ?? null;
        $browserSessionId  = $punchData['browser_session_id'] ?? null;
        $deviceWallClockAt = $punchData['device_wall_clock_at'] ?? null;
        $anchorElapsedMs   = isset($punchData['anchor_elapsed_ms']) 
            ? (int) $punchData['anchor_elapsed_ms'] 
            : (isset($punchData['monotonic_elapsed_ms']) ? (int) $punchData['monotonic_elapsed_ms'] : null);
        $browserRestarted  = !empty($punchData['browser_restarted']);

        if ($syncSource === 'DIRECT_ONLINE') {
            $clockTrust = 'TRUSTED_SERVER_TIME';
            // Online future timestamp check (> 60s in future relative to server)
            if ($punchEpochTime - $serverEpochTime > 60) {
                return [
                    'status' => 'INVALID_FUTURE_TIMESTAMP',
                    'error'  => 'Submitted timestamp is in the future. Check device system clock.',
                ];
            }
        } else {
            // Offline sync evaluations
            if ($isRetiredEpoch) {
                // Event from retired epoch held for review
                $clockTrust = 'UNVERIFIED_OFFLINE_TIME';
                $holdForReview = true;
            } elseif (empty($serverAnchorId)) {
                // Unanchored device time: missing server time anchor
                $clockTrust = 'UNVERIFIED_OFFLINE_TIME';
                $holdForReview = true;
            } else {
                $anchor = $this->db->table('kiosk_time_anchors')
                    ->where('id', $serverAnchorId)
                    ->get()
                    ->getRowArray();

                if (!$anchor) {
                    // Unknown server anchor
                    $clockTrust = 'UNVERIFIED_OFFLINE_TIME';
                    $holdForReview = true;
                } elseif ($anchor['device_id'] !== $deviceId || $anchor['device_epoch_id'] !== $deviceEpochId) {
                    // Mismatched device or mismatched device epoch on anchor
                    $clockTrust = 'UNVERIFIED_OFFLINE_TIME';
                    $holdForReview = true;
                } elseif (strtotime($anchor['expires_at']) <= $serverEpochTime) {
                    // Expired anchor
                    $clockTrust = 'UNVERIFIED_OFFLINE_TIME';
                    $holdForReview = true;
                } elseif (!empty($anchor['browser_session_hash']) && (empty($browserSessionId) || !hash_equals($anchor['browser_session_hash'], hash('sha256', (string) $browserSessionId)))) {
                    // Browser session mismatch: reload, reboot, or spoofed session
                    $clockTrust = 'UNVERIFIED_OFFLINE_TIME';
                    $holdForReview = true;
                } elseif ($browserRestarted || $anchorElapsedMs === null || $anchorElapsedMs < 0) {
                    // Monotonic anchor broken: browser reload, reboot, or missing/negative monotonic elapsed
                    $clockTrust = 'UNVERIFIED_OFFLINE_TIME';
                    $holdForReview = true;
                } elseif ($anchorElapsedMs > 172800000) {
                    // Excessive monotonic elapsed time (> 48 hours)
                    $clockTrust = 'UNVERIFIED_OFFLINE_TIME';
                    $holdForReview = true;
                } else {
                    // Derive claimed UTC time from server anchor and monotonic elapsed duration
                    $anchorUtcEpoch = strtotime($anchor['server_utc_time'] . ' UTC');
                    $expectedUtcEpoch = $anchorUtcEpoch + (int) round($anchorElapsedMs / 1000);

                    // Anomaly: Device claims historical timestamp preceding anchor creation moment
                    if ($punchEpochTime < $anchorUtcEpoch - 60) {
                        $clockTrust = 'TIME_MANIPULATED';
                        $holdForReview = true;
                    } elseif ($expectedUtcEpoch - $serverEpochTime > 5) {
                        // Future time check (> 5 seconds in future relative to server)
                        $clockTrust = 'SUSPICIOUS_DRIFT';
                        $holdForReview = true;
                    } else {
                        // Monotonic anchor is valid!
                        // Even if device wall clock was manipulated or desynchronized,
                        // the anchor-derived event time is authoritative!
                        $clockTrust = 'NORMAL_SYNC';
                        $punchEpochTime = $expectedUtcEpoch;
                        $derivedUtc = (new \DateTimeImmutable('@' . $expectedUtcEpoch))->setTimezone($utcTz);
                        $clientRecordedAtStr = $derivedUtc->format('Y-m-d H:i:s');
                        $clientRecordedIst = $derivedUtc->setTimezone($schoolTz);
                        $workDate = $clientRecordedIst->format('Y-m-d');
                        $localTimeStr = $clientRecordedIst->format('Y-m-d H:i:s');
                    }
                }
            }

            // Client wall clock manipulation and drift checks (applied when no valid monotonic anchor was used)
            if ($clockTrust !== 'NORMAL_SYNC' && $clockTrust !== 'TRUSTED_SERVER_TIME') {
                if ($queueDurationSeconds < -5) {
                    // Client clock rolled backward while in queue
                    $clockTrust = 'TIME_MANIPULATED';
                    $holdForReview = true;
                } elseif (abs($clockOffsetSeconds) > 300) {
                    // Skewed clock at sync initiation moment (> 5 minutes drift)
                    $clockTrust = 'SUSPICIOUS_DRIFT';
                    $holdForReview = true;
                }
            }
        }

        // Section 5 Policy: Automatic closed-period locking subsystem is not yet implemented.
        // We strictly DO NOT treat an arbitrary 24-hour cutoff as a closed payroll period.
        // If an explicit lock flag exists on an attendance record, it is enforced below.

        // 8. Database Transaction with Consistent Lock Ordering and Bounded Retries
        $maxRetries = 3;
        $attempt = 0;
        $txSuccess = false;
        $photoId = null;

        while ($attempt < $maxRetries && !$txSuccess) {
            $attempt++;
            try {
                $this->db->transBegin();

                // Consistent Lock Ordering: Always lock kiosk_devices first, then employees
                $this->db->query("SELECT id FROM kiosk_devices WHERE id = ? FOR UPDATE", [$deviceId]);
                $this->db->query("SELECT id FROM employees WHERE id = ? FOR UPDATE", [$employeeId]);

                // 8a. Concurrency Idempotency & Conflict Check under lock
                $existingEventInTx = $this->eventModel->where('client_uuid', $clientUuid)->first();
                if ($existingEventInTx) {
                    $this->db->transRollback();
                    if ($existingEventInTx['payload_hash'] === $payloadHash) {
                        $summary = $this->attendanceModel
                            ->where('employee_id', $employeeId)
                            ->where('work_date', $workDate)
                            ->first();

                        $existingPhoto = $this->photoModel->findByEventId($existingEventInTx['id']);

                        return [
                            'status'         => 'IDEMPOTENT_CONFIRMED',
                            'event_id'       => $existingEventInTx['id'],
                            'record_id'      => $summary['id'] ?? null,
                            'check_in_time'  => $summary['check_in_time'] ?? $localTimeStr,
                            'check_out_time' => $summary['check_out_time'] ?? null,
                            'photo_id'       => $existingPhoto['id'] ?? null,
                            'photo_is_retry' => ($existingPhoto !== null),
                            'message'        => 'Punch already recorded with identical client UUID.',
                        ];
                    } else {
                        $this->auditModel->log(
                            'SYSTEM_SECURITY',
                            'CLIENT_UUID_PAYLOAD_MISMATCH',
                            json_encode([
                                'client_uuid'   => $clientUuid,
                                'device_id'     => $deviceId,
                                'employee_id'   => $employeeId,
                                'recorded_hash' => $existingEventInTx['payload_hash'],
                                'incoming_hash' => $payloadHash,
                            ]),
                            'ATTENDANCE_EVENT',
                            $existingEventInTx['id']
                        );

                        return [
                            'status' => 'CONFLICT_PAYLOAD_MISMATCH',
                            'error'  => 'Duplicate client UUID submitted with conflicting payload content.',
                        ];
                    }
                }

                // 8b. Concurrency Sequence Collision Check under lock
                $seqConflictInTx = $this->eventModel
                    ->where('device_id', $deviceId)
                    ->where('device_epoch_id', $submittedEpochId)
                    ->where('device_event_sequence', $deviceSeq)
                    ->first();

                if ($seqConflictInTx) {
                    $this->db->transRollback();
                    $this->auditModel->log(
                        'SYSTEM_SECURITY',
                        'DEVICE_SEQUENCE_COLLISION',
                        json_encode([
                            'client_uuid'           => $clientUuid,
                            'employee_id'           => $employeeId,
                            'device_id'             => $deviceId,
                            'device_epoch_id'       => $submittedEpochId,
                            'device_event_sequence' => $deviceSeq,
                            'conflicting_event_id'  => $seqConflictInTx['id'],
                        ]),
                        'ATTENDANCE_EVENT',
                        $seqConflictInTx['id']
                    );

                    return [
                        'status' => 'QUARANTINED',
                        'error'  => 'Duplicate sequence number detected from device; event quarantined.',
                    ];
                }

                // Query daily summary for the calculated school work date
                $existing = $this->attendanceModel
                    ->where('employee_id', $employeeId)
                    ->where('work_date', $workDate)
                    ->first();

                $targetEventType = 'CHECK_IN';
                $processingStatus = 'PROCESSED';
                $notes = null;
                $summaryAction = 'NONE';
                $targetRecordId = $existing['id'] ?? null;

                // CRITICAL FAIL-SAFE RULE: If flagged for review, out-of-order, or unverified trust, DO NOT MODIFY attendance_records!
                if ($holdForReview || $isOutOfOrder || $isRetiredEpoch || in_array($clockTrust, ['UNVERIFIED_OFFLINE_TIME', 'SUSPICIOUS_DRIFT', 'TIME_MANIPULATED'], true)) {
                    $processingStatus = 'REVIEW_REQUIRED';
                    $summaryAction    = 'HELD_FOR_REVIEW';
                    $targetEventType  = ($terminalMode === 'EXIT_ONLY') ? 'CHECK_OUT' : 'CHECK_IN';
                    if ($isOutOfOrder) {
                        $notes = "Out-of-order sequence {$deviceSeq} arrived after sequence {$lastSequence}. Held for administrative review without modifying daily summary.";
                    } elseif ($isRetiredEpoch) {
                        $notes = "Event submitted from retired epoch {$submittedEpochId}. Preserved in ledger and held for administrative review.";
                    } else {
                        $notes = "Offline punch held for administrative review (Trust status: {$clockTrust}). Daily summary not modified.";
                    }
                } else {
                    // Apply Terminal Mode State Machine for TRUSTED events
                    if ($terminalMode === 'ENTRY_ONLY') {
                        $targetEventType = 'CHECK_IN';
                        if (!$existing) {
                            // Initial check-in for this work date
                            $targetRecordId = self::generateUuidV4();
                            $this->attendanceModel->insert([
                                'id'               => $targetRecordId,
                                'employee_id'      => $employeeId,
                                'work_date'        => $workDate,
                                'check_in_time'    => $localTimeStr,
                                'check_out_time'   => null,
                                'status'           => 'CHECKED_IN',
                                'confidence_score' => $confidenceScore,
                                'device_id'        => $deviceId,
                                'client_uuid'      => $clientUuid,
                                'sync_source'      => $syncSource,
                                'is_corrected'     => 0,
                                'version'          => 1,
                                'created_at'       => $serverReceivedAt,
                                'updated_at'       => $serverReceivedAt,
                            ]);
                            $processingStatus = 'PROCESSED';
                            $summaryAction    = 'CREATED';
                        } else {
                            $existingCheckInEpoch = (new \DateTimeImmutable($existing['check_in_time'], $schoolTz))->getTimestamp();
                            if ($punchEpochTime < $existingCheckInEpoch) {
                                // Out-of-order earlier offline punch wins!
                                $this->attendanceModel->update($existing['id'], [
                                    'check_in_time' => $localTimeStr,
                                    'device_id'     => $deviceId,
                                    'sync_source'   => $syncSource,
                                    'version'       => (int) $existing['version'] + 1,
                                    'updated_at'    => $serverReceivedAt,
                                ]);
                                $processingStatus = 'RECONCILED_EARLIER';
                                $summaryAction    = 'RECONCILED_EARLIER';

                                $this->auditModel->log(
                                    'ATTENDANCE_CORRECTION',
                                    'OUT_OF_ORDER_CHECK_IN_ADJUSTED',
                                    json_encode([
                                        'record_id'        => $existing['id'],
                                        'previous_checkin' => $existing['check_in_time'],
                                        'new_checkin'      => $localTimeStr,
                                        'device_id'        => $deviceId,
                                        'sync_source'      => $syncSource,
                                    ]),
                                    'ATTENDANCE_RECORD',
                                    $existing['id']
                                );
                            } else {
                                // Already checked in: continuous presence observation
                                $targetEventType  = 'PRESENCE_CONFIRMED';
                                $processingStatus = 'DUPLICATE_IGNORED';
                                $summaryAction    = 'DUPLICATE_IGNORED';
                                $notes            = 'Repeated scan at entry terminal: presence confirmed, check-in preserved.';
                            }
                        }
                    } elseif ($terminalMode === 'EXIT_ONLY') {
                        $targetEventType = 'CHECK_OUT';
                        if (!$existing || empty($existing['check_in_time'])) {
                            // Reject exit punch when no prior check-in exists today
                            $processingStatus = 'REJECTED_NO_OPEN_RECORD';
                            $summaryAction    = 'REJECTED_NO_OPEN_RECORD';
                            $notes            = 'Exit rejected: employee has no open check-in record for this work date.';
                        } else {
                            $existingCheckInEpoch = (new \DateTimeImmutable($existing['check_in_time'], $schoolTz))->getTimestamp();
                            if ($punchEpochTime <= $existingCheckInEpoch) {
                                $processingStatus = 'QUARANTINED';
                                $summaryAction    = 'QUARANTINED';
                                $notes            = 'Rejected check-out: timestamp precedes or equals check-in time.';
                            } else {
                                $this->attendanceModel->update($existing['id'], [
                                    'check_out_time' => $localTimeStr,
                                    'status'         => 'CHECKED_OUT',
                                    'version'        => (int) $existing['version'] + 1,
                                    'updated_at'     => $serverReceivedAt,
                                ]);
                                $processingStatus = 'PROCESSED';
                                $summaryAction    = 'CHECK_OUT_RECORDED';
                            }
                        }
                    } elseif ($terminalMode === 'DIRECTIONAL_AUTO') {
                        if (!$existing) {
                            // Initial check-in for this work date
                            $targetRecordId = self::generateUuidV4();
                            $this->attendanceModel->insert([
                                'id'               => $targetRecordId,
                                'employee_id'      => $employeeId,
                                'work_date'        => $workDate,
                                'check_in_time'    => $localTimeStr,
                                'check_out_time'   => null,
                                'status'           => 'CHECKED_IN',
                                'confidence_score' => $confidenceScore,
                                'device_id'        => $deviceId,
                                'client_uuid'      => $clientUuid,
                                'sync_source'      => $syncSource,
                                'is_corrected'     => 0,
                                'version'          => 1,
                                'created_at'       => $serverReceivedAt,
                                'updated_at'       => $serverReceivedAt,
                            ]);
                            $targetEventType  = 'CHECK_IN';
                            $processingStatus = 'PROCESSED';
                            $summaryAction    = 'CREATED';
                        } else {
                            // Already has attendance record for today
                            if (!empty($existing['check_out_time']) || $existing['status'] === 'CHECKED_OUT') {
                                // Already checked in and checked out: attendance completed for today
                                $targetEventType  = 'ALREADY_COMPLETED';
                                $processingStatus = 'ALREADY_COMPLETED';
                                $summaryAction    = 'DUPLICATE_IGNORED';
                                $notes            = 'Attendance already completed for today. Duplicate scan ignored.';
                            } else {
                                // Has checked in, check_out_time is empty
                                $existingCheckInEpoch = (new \DateTimeImmutable($existing['check_in_time'], $schoolTz))->getTimestamp();
                                $elapsed = $punchEpochTime - $existingCheckInEpoch;

                                $movementDirection = $punchData['movement_direction'] ?? null;
                                if ($movementDirection === 'UNKNOWN') {
                                    // Ambiguous optical movement direction: continuous presence, no check-out
                                    $targetEventType  = 'PRESENCE_CONFIRMED';
                                    $processingStatus = 'DUPLICATE_IGNORED';
                                    $summaryAction    = 'DUPLICATE_IGNORED';
                                    $notes            = 'Ambiguous movement direction on auto terminal: presence confirmed, check-in preserved.';
                                } elseif ($elapsed < 120) {
                                    // Under 2 minutes: continuous presence confirmation, do not duplicate
                                    $targetEventType  = 'ALREADY_CHECKED_IN';
                                    $processingStatus = 'ALREADY_CHECKED_IN';
                                    $summaryAction    = 'DUPLICATE_IGNORED';
                                    $notes            = 'Employee already checked in today. Immediate repeated punch ignored.';
                                } else {
                                    // Return scan after cooldown: records Check-Out / Sign-Off
                                    $this->attendanceModel->update($existing['id'], [
                                        'check_out_time' => $localTimeStr,
                                        'status'         => 'CHECKED_OUT',
                                        'version'        => (int) $existing['version'] + 1,
                                        'updated_at'     => $serverReceivedAt,
                                    ]);
                                    $targetEventType  = 'CHECK_OUT';
                                    $processingStatus = 'PROCESSED';
                                    $summaryAction    = 'CHECK_OUT_RECORDED';
                                }
                            }
                        }
                    }
                }

                // Ingest into append-only attendance_events ledger
                $eventId = self::generateUuidV4();
                $inserted = $this->eventModel->insert([
                    'id'                     => $eventId,
                    'client_uuid'            => $clientUuid,
                    'employee_id'            => $employeeId,
                    'device_id'              => $deviceId,
                    'device_epoch_id'        => $submittedEpochId ?? $deviceEpochId,
                    'device_event_sequence'  => $deviceSeq,
                    'event_type'             => $targetEventType,
                    'terminal_mode'          => $terminalMode,
                    'client_recorded_at'     => $clientRecordedAtStr,
                    'client_synced_at'       => $clientSyncedAtStr,
                    'server_received_at'     => $serverReceivedAt,
                    'client_timezone'        => $clientTimezone,
                    'client_monotonic_ms'    => $clientMonotonicMs,
                    'server_anchor_id'       => $serverAnchorId,
                    'browser_session_id'     => $browserSessionId,
                    'device_wall_clock_at'   => $deviceWallClockAt,
                    'anchor_elapsed_ms'      => $anchorElapsedMs,
                    'clock_trust_status'     => $clockTrust,
                    'synchronization_source' => $syncSource,
                    'payload_hash'           => $payloadHash,
                    'processing_status'      => $processingStatus,
                    'notes'                  => $notes,
                    'created_at'             => $serverReceivedAt,
                ]);

                if ($inserted === false) {
                    $dbErr = $this->db->error();
                    throw new \Exception($dbErr['message'] ?? 'Ledger insert failed', (int) ($dbErr['code'] ?? 0));
                }

                // 8c. Photo Association inside Atomic Transaction
                if (!empty($photoMetadata) && is_array($photoMetadata)) {
                    if ($summaryAction === 'DUPLICATE_IGNORED' || $targetEventType === 'PRESENCE_CONFIRMED') {
                        // Continuous presence scan: preserve existing check-in photo, do not create duplicate photo record
                        $existingCheckInPhoto = $this->photoModel->findByRecordAndType($targetRecordId, 'CHECK_IN');
                        $photoId = $existingCheckInPhoto['id'] ?? null;
                    } elseif (!in_array($summaryAction, ['REJECTED_NO_OPEN_RECORD', 'QUARANTINED'], true)) {
                        $photoId = self::generateUuidV4();
                        $photoType = ($targetEventType === 'CHECK_OUT') ? 'CHECK_OUT' : 'CHECK_IN';
                        $photoInserted = $this->photoModel->insert([
                            'id'                   => $photoId,
                            'event_id'             => $eventId,
                            'attendance_record_id' => $targetRecordId,
                            'employee_id'          => $employeeId,
                            'photo_type'           => $photoType,
                            'storage_path'         => $photoMetadata['storage_path'],
                            'file_name'            => $photoMetadata['file_name'],
                            'mime_type'            => $photoMetadata['mime_type'] ?? 'image/jpeg',
                            'file_size'            => (int) ($photoMetadata['file_size'] ?? 0),
                            'image_width'          => (int) ($photoMetadata['image_width'] ?? 0),
                            'image_height'         => (int) ($photoMetadata['image_height'] ?? 0),
                            'created_at'           => $serverReceivedAt,
                            'updated_at'           => $serverReceivedAt,
                        ]);

                        if ($photoInserted === false) {
                            $photoErr = $this->db->error();
                            throw new \Exception('Photo metadata insert failed: ' . ($photoErr['message'] ?? 'Database error'), (int) ($photoErr['code'] ?? 0));
                        }
                    }
                }

                // Update device sequence progression only for current epoch and forward progressions
                if (!$isRetiredEpoch && !$isOutOfOrder) {
                    $newMaxSeq = max($lastSequence, $deviceSeq);
                    $this->db->table('kiosk_devices')
                        ->where('id', $deviceId)
                        ->update([
                            'last_event_sequence' => $newMaxSeq,
                            'last_seen_at'        => $serverReceivedAt,
                        ]);
                } else {
                    $this->db->table('kiosk_devices')
                        ->where('id', $deviceId)
                        ->update([
                            'last_seen_at' => $serverReceivedAt,
                        ]);
                }

                $this->db->transCommit();
                $txSuccess = true;
            } catch (\Throwable $e) {
                $this->db->transRollback();

                // Deadlock / lock timeout retry
                if (($e->getCode() == 1213 || str_contains($e->getMessage(), 'Deadlock') || str_contains($e->getMessage(), 'Lock wait timeout')) && $attempt < $maxRetries) {
                    usleep(rand(10000, 40000)); // 10ms - 40ms jittered backoff
                    continue;
                }

                // Sequence collision race on uk_att_events_device_seq
                if (str_contains($e->getMessage(), 'uk_att_events_device_seq')) {
                    return [
                        'status' => 'QUARANTINED',
                        'error'  => 'Duplicate sequence number detected from device; event quarantined.',
                    ];
                }

                // Client UUID race on uk_att_events_client_uuid
                if (str_contains($e->getMessage(), 'uk_att_events_client_uuid') || str_contains($e->getMessage(), 'Duplicate entry')) {
                    $raceEvent = $this->eventModel->where('client_uuid', $clientUuid)->first();
                    if ($raceEvent && $raceEvent['payload_hash'] === $payloadHash) {
                        $racePhoto = $this->photoModel->findByEventId($raceEvent['id']);
                        return [
                            'status'         => 'IDEMPOTENT_CONFIRMED',
                            'event_id'       => $raceEvent['id'],
                            'photo_id'       => $racePhoto['id'] ?? null,
                            'photo_is_retry' => ($racePhoto !== null),
                            'message'        => 'Punch already recorded with identical client UUID (concurrency race resolved).',
                        ];
                    }
                    return [
                        'status' => 'CONFLICT_PAYLOAD_MISMATCH',
                        'error'  => 'Duplicate client UUID race detected with conflicting payload.',
                    ];
                }

                return [
                    'status' => 'DATABASE_ERROR',
                    'error'  => 'Database exception during attendance ingestion: ' . $e->getMessage(),
                ];
            }
        }

        // Fetch refreshed summary
        $latestSummary = $this->attendanceModel
            ->where('employee_id', $employeeId)
            ->where('work_date', $workDate)
            ->first();

        return [
            'status'         => $summaryAction,
            'event_id'       => $eventId,
            'record_id'      => $latestSummary['id'] ?? $targetRecordId,
            'work_date'      => $workDate,
            'check_in_time'  => $latestSummary['check_in_time'] ?? null,
            'check_out_time' => $latestSummary['check_out_time'] ?? null,
            'terminal_mode'  => $terminalMode,
            'event_type'     => $targetEventType,
            'photo_id'       => $photoId,
            'message'        => $notes ?: 'Attendance event processed successfully.',
        ];
    }

    /**
     * Calculate anchor-derived event UTC time string
     */
    public function calculateAnchorDerivedUtc(array $event): string
    {
        if (empty($event['server_anchor_id']) || !isset($event['anchor_elapsed_ms'])) {
            return $event['client_recorded_at'];
        }

        $anchor = $this->db->table('kiosk_time_anchors')
            ->where('id', $event['server_anchor_id'])
            ->get()
            ->getRowArray();

        if (!$anchor) {
            return $event['client_recorded_at'];
        }

        $anchorUtcEpoch = strtotime($anchor['server_utc_time'] . ' UTC');
        $expectedUtcEpoch = $anchorUtcEpoch + (int) round($event['anchor_elapsed_ms'] / 1000);

        return gmdate('Y-m-d H:i:s', $expectedUtcEpoch);
    }

    /**
     * Approve a held attendance event and apply attendance transitions
     */
    public function approveHeldEvent(string $eventId, string $adminUserId, string $adminUsername, string $reason): array
    {
        $reason = trim($reason);
        if (empty($reason) || strlen($reason) < 5) {
            return [
                'status' => 'INVALID_REASON',
                'error'  => 'A substantive review reason (minimum 5 characters) is required for approval.',
            ];
        }

        $this->db->transBegin();

        try {
            // 1. Lock event row FIRST to serialize all concurrent reviews on this event
            $event = $this->db->query("SELECT * FROM attendance_events WHERE id = ? FOR UPDATE", [$eventId])->getRowArray();
            if (!$event) {
                $this->db->transRollback();
                return [
                    'status' => 'EVENT_NOT_FOUND',
                    'error'  => 'Attendance event not found.',
                ];
            }

            if ($event['processing_status'] !== 'REVIEW_REQUIRED') {
                $this->db->transRollback();
                return [
                    'status' => 'EVENT_NOT_HELD',
                    'error'  => 'Only events with status REVIEW_REQUIRED can be approved.',
                ];
            }

            // 2. Under event lock, verify no review decision already exists
            $existingDecision = $this->db->query("SELECT * FROM attendance_review_decisions WHERE event_id = ? FOR UPDATE", [$eventId])->getRowArray();
            if ($existingDecision) {
                $this->db->transRollback();
                return [
                    'status'   => 'DUPLICATE_DECISION',
                    'decision' => $existingDecision['decision'],
                    'error'    => "This event has already been reviewed with decision: {$existingDecision['decision']}.",
                ];
            }

            // 3. Lock device and employee rows in deterministic order
            $this->db->query("SELECT id FROM kiosk_devices WHERE id = ? FOR UPDATE", [$event['device_id']]);
            $this->db->query("SELECT id FROM employees WHERE id = ? FOR UPDATE", [$event['employee_id']]);

            // 4. Calculate local work date and local timestamp to apply
            $schoolTz = new \DateTimeZone('Asia/Kolkata');
            $utcTz    = new \DateTimeZone('UTC');

            $effectiveUtcStr = $this->calculateAnchorDerivedUtc($event);
            $effectiveUtc    = new \DateTimeImmutable($effectiveUtcStr, $utcTz);
            $localDt         = $effectiveUtc->setTimezone($schoolTz);
            $workDate        = $localDt->format('Y-m-d');
            $localTimeStr    = $localDt->format('Y-m-d H:i:s');
            $now             = date('Y-m-d H:i:s');

            // 5. Lock and apply attendance transition to attendance_records
            $summary = $this->db->query("
                SELECT * FROM attendance_records 
                WHERE employee_id = ? AND work_date = ? 
                FOR UPDATE
            ", [$event['employee_id'], $workDate])->getRowArray();

            $appliedRecordId = null;

            if ($event['event_type'] === 'CHECK_IN' || $event['terminal_mode'] === 'ENTRY_ONLY') {
                if (!$summary) {
                    $appliedRecordId = self::generateUuidV4();
                    $this->attendanceModel->insert([
                        'id'               => $appliedRecordId,
                        'employee_id'      => $event['employee_id'],
                        'work_date'        => $workDate,
                        'check_in_time'    => $localTimeStr,
                        'check_out_time'   => null,
                        'status'           => 'CHECKED_IN',
                        'confidence_score' => 0.90,
                        'device_id'        => $event['device_id'],
                        'client_uuid'      => $event['client_uuid'],
                        'sync_source'      => $event['synchronization_source'] ?? 'OFFLINE_SYNC',
                        'is_corrected'     => 1,
                        'version'          => 1,
                        'created_at'       => $now,
                        'updated_at'       => $now,
                    ]);
                } else {
                    $appliedRecordId = $summary['id'];
                    $existingCheckInEpoch = (new \DateTimeImmutable($summary['check_in_time'], $schoolTz))->getTimestamp();
                    $approvedEpoch = $localDt->getTimestamp();
                    if ($approvedEpoch < $existingCheckInEpoch) {
                        $this->attendanceModel->update($appliedRecordId, [
                            'check_in_time' => $localTimeStr,
                            'is_corrected'  => 1,
                            'version'       => (int) $summary['version'] + 1,
                            'updated_at'    => $now,
                        ]);
                    }
                }
            } elseif ($event['event_type'] === 'CHECK_OUT' || $event['terminal_mode'] === 'EXIT_ONLY') {
                if (!$summary) {
                    $appliedRecordId = self::generateUuidV4();
                    $this->attendanceModel->insert([
                        'id'               => $appliedRecordId,
                        'employee_id'      => $event['employee_id'],
                        'work_date'        => $workDate,
                        'check_in_time'    => null,
                        'check_out_time'   => $localTimeStr,
                        'status'           => 'CHECKED_OUT',
                        'confidence_score' => 0.90,
                        'device_id'        => $event['device_id'],
                        'client_uuid'      => $event['client_uuid'],
                        'sync_source'      => $event['synchronization_source'] ?? 'OFFLINE_SYNC',
                        'is_corrected'     => 1,
                        'version'          => 1,
                        'created_at'       => $now,
                        'updated_at'       => $now,
                    ]);
                } else {
                    $appliedRecordId = $summary['id'];
                    $this->attendanceModel->update($appliedRecordId, [
                        'check_out_time' => $localTimeStr,
                        'status'         => 'CHECKED_OUT',
                        'is_corrected'   => 1,
                        'version'        => (int) $summary['version'] + 1,
                        'updated_at'     => $now,
                    ]);
                }
            }

            // 6. Record decision in attendance_review_decisions (preserving original event in attendance_events)
            $decisionId = self::generateUuidV4();
            $this->db->table('attendance_review_decisions')->insert([
                'id'                => $decisionId,
                'event_id'          => $eventId,
                'decision'          => 'APPROVED',
                'reviewer_id'       => $adminUserId,
                'reviewer_username' => $adminUsername,
                'review_reason'     => $reason,
                'applied_record_id' => $appliedRecordId,
                'decided_at'        => $now,
                'created_at'        => $now,
            ]);

            // 7. Audit log
            $this->auditModel->log(
                $adminUsername,
                'ATTENDANCE_EVENT_APPROVED',
                json_encode([
                    'event_id'          => $eventId,
                    'decision_id'       => $decisionId,
                    'reviewer_id'       => $adminUserId,
                    'reviewer_username' => $adminUsername,
                    'reason'            => $reason,
                    'work_date'         => $workDate,
                    'applied_record_id' => $appliedRecordId,
                ]),
                'ATTENDANCE_EVENT',
                $eventId
            );

            $this->db->transCommit();

            return [
                'status'            => 'APPROVED',
                'decision_id'       => $decisionId,
                'event_id'          => $eventId,
                'applied_record_id' => $appliedRecordId,
                'message'           => 'Event successfully approved and attendance aggregate updated.',
            ];
        } catch (\Throwable $e) {
            $this->db->transRollback();
            if (str_contains($e->getMessage(), 'Duplicate entry') && (str_contains($e->getMessage(), 'event_id') || str_contains($e->getMessage(), 'PRIMARY'))) {
                $dec = $this->db->table('attendance_review_decisions')->where('event_id', $eventId)->get()->getRowArray();
                return [
                    'status'   => 'DUPLICATE_DECISION',
                    'decision' => $dec['decision'] ?? 'ALREADY_DECIDED',
                    'error'    => 'This event has already been reviewed (concurrency race resolved).',
                ];
            }
            return [
                'status' => 'ERROR',
                'error'  => 'Approval transaction failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Reject a held attendance event
     */
    public function rejectHeldEvent(string $eventId, string $adminUserId, string $adminUsername, string $reason): array
    {
        $reason = trim($reason);
        if (empty($reason) || strlen($reason) < 5) {
            return [
                'status' => 'INVALID_REASON',
                'error'  => 'A substantive review reason (minimum 5 characters) is required for rejection.',
            ];
        }

        $this->db->transBegin();

        try {
            // 1. Lock event row FIRST to serialize all concurrent reviews on this event
            $event = $this->db->query("SELECT * FROM attendance_events WHERE id = ? FOR UPDATE", [$eventId])->getRowArray();
            if (!$event) {
                $this->db->transRollback();
                return [
                    'status' => 'EVENT_NOT_FOUND',
                    'error'  => 'Attendance event not found.',
                ];
            }

            if ($event['processing_status'] !== 'REVIEW_REQUIRED') {
                $this->db->transRollback();
                return [
                    'status' => 'EVENT_NOT_HELD',
                    'error'  => 'Only events with status REVIEW_REQUIRED can be rejected.',
                ];
            }

            // 2. Under event lock, verify no review decision already exists
            $existingDecision = $this->db->query("SELECT * FROM attendance_review_decisions WHERE event_id = ? FOR UPDATE", [$eventId])->getRowArray();
            if ($existingDecision) {
                $this->db->transRollback();
                return [
                    'status'   => 'DUPLICATE_DECISION',
                    'decision' => $existingDecision['decision'],
                    'error'    => "This event has already been reviewed with decision: {$existingDecision['decision']}.",
                ];
            }

            $now = date('Y-m-d H:i:s');
            $decisionId = self::generateUuidV4();

            // 3. Record decision in attendance_review_decisions
            $this->db->table('attendance_review_decisions')->insert([
                'id'                => $decisionId,
                'event_id'          => $eventId,
                'decision'          => 'REJECTED',
                'reviewer_id'       => $adminUserId,
                'reviewer_username' => $adminUsername,
                'review_reason'     => $reason,
                'applied_record_id' => null,
                'decided_at'        => $now,
                'created_at'        => $now,
            ]);

            // 4. Audit log
            $this->auditModel->log(
                $adminUsername,
                'ATTENDANCE_EVENT_REJECTED',
                json_encode([
                    'event_id'          => $eventId,
                    'decision_id'       => $decisionId,
                    'reviewer_id'       => $adminUserId,
                    'reviewer_username' => $adminUsername,
                    'reason'            => $reason,
                ]),
                'ATTENDANCE_EVENT',
                $eventId
            );

            // Daily summary (attendance_records) and original event (attendance_events) remain 100% untouched!

            $this->db->transCommit();

            return [
                'status'      => 'REJECTED',
                'decision_id' => $decisionId,
                'event_id'    => $eventId,
                'message'     => 'Event successfully rejected. Official attendance aggregate remains unchanged.',
            ];
        } catch (\Throwable $e) {
            $this->db->transRollback();
            if (str_contains($e->getMessage(), 'Duplicate entry') && (str_contains($e->getMessage(), 'event_id') || str_contains($e->getMessage(), 'PRIMARY'))) {
                $dec = $this->db->table('attendance_review_decisions')->where('event_id', $eventId)->get()->getRowArray();
                return [
                    'status'   => 'DUPLICATE_DECISION',
                    'decision' => $dec['decision'] ?? 'ALREADY_DECIDED',
                    'error'    => 'This event has already been reviewed (concurrency race resolved).',
                ];
            }
            return [
                'status' => 'ERROR',
                'error'  => 'Rejection transaction failed: ' . $e->getMessage(),
            ];
        }
    }

    public static function generateUuidV4(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr(ord($bytes[6]) & 0x0f | 0x40);
        $bytes[8] = chr(ord($bytes[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
