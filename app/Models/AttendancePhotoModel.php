<?php

namespace App\Models;

use CodeIgniter\Model;

class AttendancePhotoModel extends Model
{
    protected $table            = 'attendance_photos';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = false;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'id',
        'event_id',
        'attendance_record_id',
        'employee_id',
        'photo_type',
        'storage_path',
        'file_name',
        'mime_type',
        'file_size',
        'image_width',
        'image_height',
        'created_at',
        'updated_at',
    ];

    // Dates
    protected $useTimestamps = false; // Manually controlled or explicit timestamps
    protected $dateFormat    = 'datetime';

    // Validation
    protected $validationRules = [
        'id'           => 'required|exact_length[36]',
        'event_id'     => 'required|exact_length[36]',
        'employee_id'  => 'required|exact_length[36]',
        'photo_type'   => 'required|in_list[CHECK_IN,CHECK_OUT]',
        'storage_path' => 'required|max_length[255]',
        'file_name'    => 'required|max_length[128]',
        'mime_type'    => 'required|max_length[64]',
        'file_size'    => 'required|is_natural',
        'image_width'  => 'required|is_natural',
        'image_height' => 'required|is_natural',
    ];

    /**
     * Find photo record by event UUID.
     */
    public function findByEventId(string $eventId): ?array
    {
        return $this->where('event_id', $eventId)->first();
    }

    /**
     * Find photo by attendance record ID and photo type (CHECK_IN or CHECK_OUT).
     */
    public function findByRecordAndType(string $recordId, string $photoType): ?array
    {
        return $this->where('attendance_record_id', $recordId)
                    ->where('photo_type', $photoType)
                    ->first();
    }

    /**
     * Batch load photos for a collection of attendance record IDs in ONE query.
     * Prevents N+1 query patterns on attendance history and reporting tables.
     *
     * @param string[] $recordIds
     * @return array<string, array{CHECK_IN: ?array, CHECK_OUT: ?array}> Map of [recordId => [CHECK_IN => photo, CHECK_OUT => photo]]
     */
    public function getPhotosByRecordIds(array $recordIds): array
    {
        if (empty($recordIds)) {
            return [];
        }

        $photos = $this->whereIn('attendance_record_id', $recordIds)->findAll();

        $map = [];
        foreach ($recordIds as $rid) {
            $map[$rid] = [
                'CHECK_IN'  => null,
                'CHECK_OUT' => null,
            ];
        }

        foreach ($photos as $p) {
            $rid = $p['attendance_record_id'];
            $type = $p['photo_type'];
            if (isset($map[$rid])) {
                $map[$rid][$type] = $p;
            }
        }

        return $map;
    }
}

