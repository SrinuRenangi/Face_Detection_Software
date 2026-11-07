<?php

namespace App\Services;

use CodeIgniter\HTTP\Files\UploadedFile;

class AttendancePhotoService
{
    public const MAX_FILE_SIZE_BYTES = 2097152; // 2 MB
    public const MIN_DIMENSION = 200;           // 200 px
    public const MAX_DIMENSION = 4000;          // 4000 px
    public const DEFAULT_JPEG_QUALITY = 85;

    protected string $baseStorageDir;

    public function __construct(?string $baseStorageDir = null)
    {
        $this->baseStorageDir = $baseStorageDir ?? (rtrim(WRITEPATH, '/\\') . DIRECTORY_SEPARATOR . 'attendance_photos');
        if (!is_dir($this->baseStorageDir)) {
            mkdir($this->baseStorageDir, 0750, true);
        }
    }

    /**
     * Extracts raw binary bytes from an UploadedFile, a Base64 string, or raw bytes.
     *
     * @param mixed $input
     * @return string|null Raw binary data or null if invalid
     */
    public function extractBinaryData($input): ?string
    {
        if ($input instanceof UploadedFile) {
            if (!$input->isValid() || $input->hasMoved()) {
                return null;
            }
            $content = file_get_contents($input->getTempName());
            return ($content !== false) ? $content : null;
        }

        if (is_string($input)) {
            $trimmed = trim($input);
            if (empty($trimmed)) {
                return null;
            }

            // Check if input is Data URL: data:image/...;base64,xxxx
            if (preg_match('/^data:image\/[a-zA-Z0-9_\.\-+]+;base64,(.+)$/s', $trimmed, $matches)) {
                $decoded = base64_decode($matches[1], true);
                return ($decoded !== false) ? $decoded : null;
            }

            // Check if input is raw Base64 without data URL scheme
            // Base64 strings will only contain [A-Za-z0-9+/=] and whitespace
            if (preg_match('/^[A-Za-z0-9+\/=\s]+$/', $trimmed) && strlen($trimmed) % 4 === 0 && strlen($trimmed) > 100) {
                $decoded = base64_decode($trimmed, true);
                if ($decoded !== false && (substr($decoded, 0, 2) === "\xFF\xD8" || substr($decoded, 0, 8) === "\x89PNG\r\n\x1a\n")) {
                    return $decoded;
                }
            }

            // Otherwise check if it's already binary image bytes
            return $trimmed;
        }

        return null;
    }

    /**
     * Validates binary photograph content.
     * Enforces size, JPEG magic bytes, decodability, and dimension bounds.
     *
     * @param string $binaryData
     * @return array{valid: bool, error: ?string, width: int, height: int, resource: mixed}
     */
    public function validatePhotoBytes(string $binaryData): array
    {
        $len = strlen($binaryData);
        if ($len === 0) {
            return [
                'valid'    => false,
                'error'    => 'Photo file is empty. Please capture and confirm a photograph.',
                'width'    => 0,
                'height'   => 0,
                'resource' => null,
            ];
        }

        if ($len > self::MAX_FILE_SIZE_BYTES) {
            $maxMb = round(self::MAX_FILE_SIZE_BYTES / (1024 * 1024), 1);
            return [
                'valid'    => false,
                'error'    => "Photo file size ({$len} bytes) exceeds the maximum allowed limit of {$maxMb} MB.",
                'width'    => 0,
                'height'   => 0,
                'resource' => null,
            ];
        }

        // Magic bytes verification: JPEG starts with \xFF\xD8\xFF, PNG starts with \x89PNG\r\n\x1a\n
        $isJpeg = ($len >= 3 && substr($binaryData, 0, 3) === "\xFF\xD8\xFF");
        $isPng  = ($len >= 8 && substr($binaryData, 0, 8) === "\x89PNG\r\n\x1a\n");

        if (!$isJpeg && !$isPng) {
            return [
                'valid'    => false,
                'error'    => 'Invalid image file signature. Only standard JPEG and PNG photographs are permitted.',
                'width'    => 0,
                'height'   => 0,
                'resource' => null,
            ];
        }

        // Image decodability and dimension check
        $imageInfo = @getimagesizefromstring($binaryData);
        if ($imageInfo === false || !in_array($imageInfo[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG], true)) {
            return [
                'valid'    => false,
                'error'    => 'Invalid image content. The uploaded file is not a decodable JPEG or PNG photograph.',
                'width'    => 0,
                'height'   => 0,
                'resource' => null,
            ];
        }

        $width = (int) $imageInfo[0];
        $height = (int) $imageInfo[1];

        if ($width < self::MIN_DIMENSION || $height < self::MIN_DIMENSION) {
            return [
                'valid'    => false,
                'error'    => "Image resolution ({$width}x{$height}) is too small. Minimum resolution required is " . self::MIN_DIMENSION . 'x' . self::MIN_DIMENSION . ' pixels.',
                'width'    => $width,
                'height'   => $height,
                'resource' => null,
            ];
        }

        if ($width > self::MAX_DIMENSION || $height > self::MAX_DIMENSION) {
            return [
                'valid'    => false,
                'error'    => "Image resolution ({$width}x{$height}) exceeds the maximum allowed resolution of " . self::MAX_DIMENSION . 'x' . self::MAX_DIMENSION . ' pixels.',
                'width'    => $width,
                'height'   => $height,
                'resource' => null,
            ];
        }

        // Verify full decodability into GD resource
        $gd = @imagecreatefromstring($binaryData);
        if (!$gd) {
            return [
                'valid'    => false,
                'error'    => 'Corrupt or malformed image data. Failed to decode image raster.',
                'width'    => $width,
                'height'   => $height,
                'resource' => null,
            ];
        }

        return [
            'valid'    => true,
            'error'    => null,
            'width'    => $width,
            'height'   => $height,
            'resource' => $gd,
        ];
    }

    /**
     * Validates and saves a photograph into private storage.
     * Strips dangerous EXIF metadata by re-encoding through GD.
     *
     * @param string $binaryData
     * @return array Metadata array on success, or array with 'error' on failure
     */
    public function storeValidatedPhoto(string $binaryData): array
    {
        $validation = $this->validatePhotoBytes($binaryData);
        if (!$validation['valid']) {
            return [
                'success' => false,
                'error'   => $validation['error'],
            ];
        }

        $gdResource = $validation['resource'];
        $width      = $validation['width'];
        $height     = $validation['height'];

        // Generate private partitioned path: attendance_photos/YYYY/MM/
        $yearMonth = date('Y/m');
        $relativeDir = 'attendance_photos/' . $yearMonth;
        $absoluteDir = rtrim(WRITEPATH, '/\\') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativeDir);

        if (!is_dir($absoluteDir)) {
            mkdir($absoluteDir, 0750, true);
        }

        // Generate unguessable random filename (never trust user input)
        $randomName = 'photo_' . bin2hex(random_bytes(16)) . '.jpg';
        $relativeStoragePath = $relativeDir . '/' . $randomName;
        $absoluteStoragePath = $absoluteDir . DIRECTORY_SEPARATOR . $randomName;

        // Create a truecolor canvas with a solid white background to normalize transparent PNGs cleanly
        $normalizedCanvas = imagecreatetruecolor($width, $height);
        $white = imagecolorallocate($normalizedCanvas, 255, 255, 255);
        imagefilledrectangle($normalizedCanvas, 0, 0, $width, $height, $white);
        imagecopy($normalizedCanvas, $gdResource, 0, 0, 0, 0, $width, $height);
        imagedestroy($gdResource);

        // Re-encode to remove EXIF metadata and write normalized JPEG to disk
        $saved = @imagejpeg($normalizedCanvas, $absoluteStoragePath, self::DEFAULT_JPEG_QUALITY);
        imagedestroy($normalizedCanvas);

        if (!$saved || !file_exists($absoluteStoragePath)) {
            return [
                'success' => false,
                'error'   => 'Failed to write attendance photograph to secure local storage.',
            ];
        }

        $fileSize = (int) filesize($absoluteStoragePath);

        return [
            'success'      => true,
            'storage_path' => $relativeStoragePath,
            'file_name'    => $randomName,
            'mime_type'    => 'image/jpeg',
            'file_size'    => $fileSize,
            'image_width'  => $validation['width'],
            'image_height' => $validation['height'],
        ];
    }

    /**
     * Resolves and verifies an absolute filesystem path for an authorized photo request.
     * Guards strictly against directory traversal.
     *
     * @param string $relativeStoragePath
     * @return string|null Absolute path if valid and exists; null otherwise
     */
    public function resolveSecurePath(string $relativeStoragePath): ?string
    {
        if (empty($relativeStoragePath)) {
            return null;
        }

        // Basic normalization
        $relativeStoragePath = str_replace(['..', "\0"], '', $relativeStoragePath);
        $candidate = rtrim(WRITEPATH, '/\\') . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativeStoragePath);

        $realBase = realpath($this->baseStorageDir);
        $realCandidate = realpath($candidate);

        if (!$realBase || !$realCandidate || !is_file($realCandidate)) {
            return null;
        }

        // Ensure candidate resides strictly within the base storage root
        if (!str_starts_with($realCandidate, $realBase . DIRECTORY_SEPARATOR)) {
            return null; // Traversal attempt rejected
        }

        return $realCandidate;
    }

    /**
     * Safely unlinks a private physical photo file.
     *
     * @param string $relativeStoragePath
     * @return bool
     */
    public function deletePhysicalFile(string $relativeStoragePath): bool
    {
        $realPath = $this->resolveSecurePath($relativeStoragePath);
        if ($realPath && is_file($realPath)) {
            return @unlink($realPath);
        }
        return false;
    }
}

