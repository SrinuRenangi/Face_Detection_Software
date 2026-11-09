<?php

namespace App\Models;

use CodeIgniter\Model;

class SystemSettingModel extends Model
{
    protected $table            = 'system_settings';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = false;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'id',
        'setting_key',
        'setting_value',
        'description',
        'updated_by',
        'created_at',
        'updated_at',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    /**
     * Get a setting by key with optional default
     */
    public function getSetting(string $key, ?string $default = null): ?string
    {
        $row = $this->where('setting_key', $key)->first();
        return $row !== null ? $row['setting_value'] : $default;
    }

    /**
     * Set / update a setting by key
     */
    public function setSetting(string $key, ?string $value, ?string $updatedBy = null, ?string $description = null): bool
    {
        $existing = $this->where('setting_key', $key)->first();
        $now = date('Y-m-d H:i:s');

        if ($existing) {
            $data = [
                'setting_value' => $value,
                'updated_at'    => $now,
            ];
            if ($updatedBy !== null) {
                $data['updated_by'] = $updatedBy;
            }
            if ($description !== null) {
                $data['description'] = $description;
            }
            return (bool) $this->update($existing['id'], $data);
        }

        $id = EmployeeModel::generateUuidV4();
        return (bool) $this->insert([
            'id'            => $id,
            'setting_key'   => $key,
            'setting_value' => $value,
            'description'   => $description,
            'updated_by'    => $updatedBy ?? 'SYSTEM',
            'created_at'    => $now,
            'updated_at'    => $now,
        ]);
    }

    /**
     * Checks if the application-wide emergency killswitch is engaged
     */
    public function isKillswitchEngaged(): bool
    {
        $val = $this->getSetting('app_killswitch_active', '0');
        return in_array($val, ['1', 'true', 'yes', 'on'], true);
    }

    /**
     * Returns an associative key => value array of all settings
     */
    public function getAllAsKeyValue(): array
    {
        $rows = $this->findAll();
        $assoc = [];
        foreach ($rows as $row) {
            $assoc[$row['setting_key']] = $row['setting_value'];
        }
        return $assoc;
    }
}

