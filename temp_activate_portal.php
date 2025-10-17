<?php
// Script สำหรับการ Activate โมดูล CustomerPortal ผ่าน URL (ใช้ชั่วคราว)

// 1. กำหนดค่าสภาพแวดล้อม vTiger (จำเป็น)
// ต้องมีการ Include ไฟล์ config.inc.php ของ CRM หลัก
include_once 'config.inc.php';

// 2. เรียกใช้ Vtiger_Module_Model และ Vtiger_Loader
require_once 'modules/Vtiger/CRMEntity.php';
require_once 'include/utils/utils.php';

// 3. กำหนดชื่อโมดูลที่เราต้องการ Activate
$moduleName = 'CustomerPortal';

// 4. โหลดโมดูล
$moduleModel = Vtiger_Module_Model::getInstance($moduleName);

// 5. บังคับ Activate/Update Database Schema
if($moduleModel) {
    // นี่คือส่วนที่สำคัญที่สุด: บังคับให้ระบบรัน Schema Script
    $moduleModel->set('state', 1); // ตั้งค่าสถานะเป็น Active
    $moduleModel->save(); // บันทึกและรันการเปลี่ยนแปลง Schema

    echo "Module '{$moduleName}' successfully activated and database schema updated. Check the 'vtiger_customerportal_contact' table now.";
} else {
    echo "Module '{$moduleName}' not found or already active. Please check module name.";
}

// 6. การทำความสะอาด (สำหรับความปลอดภัย)
// **คำเตือน: ต้องลบไฟล์นี้ทิ้งทันทีหลังจากใช้งาน**
?>
