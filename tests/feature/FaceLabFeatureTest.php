<?php

namespace Tests\Feature;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;

class FaceLabFeatureTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    public function testFaceLabRequiresAdminAuthentication()
    {
        $result = $this->get('/face-lab');
        $result->assertStatus(302);
        $result->assertRedirectTo('/admin/login');
    }

    public function testAdminRoleCanAccessFaceLab()
    {
        $sessionData = [
            'admin_logged_in' => true,
            'admin_user_id'   => 'a0000000-0000-0000-0000-000000000001',
            'admin_username'  => 'admin',
            'admin_full_name' => 'System Administrator',
            'admin_role'      => 'ROLE_ADMIN',
        ];

        $result = $this->withSession($sessionData)->get('/face-lab');
        $result->assertStatus(200);
        $result->assertSee('Stage 3 Face Lab');
        $result->assertSee('Live Camera Feed');
        $result->assertSee('face-api.js');
        $result->assertSee('Passive Liveness');
    }

    public function testManagerRoleIsDeniedFaceLabWith403()
    {
        $sessionData = [
            'admin_logged_in' => true,
            'admin_user_id'   => 'a0000000-0000-0000-0000-000000000002',
            'admin_username'  => 'manager',
            'admin_full_name' => 'Duty Manager',
            'admin_role'      => 'ROLE_MANAGER',
        ];

        $result = $this->withSession($sessionData)->get('/face-lab');
        $result->assertStatus(403);
        $result->assertSee('403');
    }

    public function testLocalLibraryFileIsPhysicallyPresent()
    {
        $jsPath = FCPATH . 'js/face-api.js';
        $this->assertFileExists($jsPath);
        $this->assertGreaterThan(1000000, filesize($jsPath)); // > 1 MB
    }

    public function testLocalModelFilesAreServedFromPublicModels()
    {
        // Face recognition neural network models served for client-side edge recognition
        $modelsDir = FCPATH . 'models/';
        $expectedFiles = [
            'tiny_face_detector_model-weights_manifest.json',
            'tiny_face_detector_model.bin',
            'face_landmark_68_model-weights_manifest.json',
            'face_landmark_68_model.bin',
            'face_recognition_model-weights_manifest.json',
            'face_recognition_model.bin',
        ];

        foreach ($expectedFiles as $file) {
            $this->assertFileExists($modelsDir . $file, "Model file {$file} must be available in public/models/.");
        }

        // Recognition model weights should be ~6.4 MB
        $recModelSize = filesize($modelsDir . 'face_recognition_model.bin');
        $this->assertGreaterThan(6000000, $recModelSize);
    }
}

