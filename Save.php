<?php
// Fix 8: เพิ่ม Debug และ Hardcode Logic
class Contacts_Save_Action extends Vtiger_Save_Action {

	public function process(Vtiger_Request $request) {
		// บังคับแสดง Error เพื่อการ Debug
		ini_set('display_errors', '1');
		ini_set('display_startup_errors', '1');
		error_reporting(E_ALL);

		//To stop saveing the value of salutation as '--None--'
		$salutationType = $request->get('salutationtype');
		if ($salutationType === '--None--') {
			$request->set('salutationtype', '');
		}
		
		// 1. เรียกใช้การ Save มาตรฐานของ Vtiger (บันทึก Record ใน DB)
		parent::process($request);
        
        // 2. ดึง Record Model ที่ถูกบันทึกมาแล้ว
        $recordId = $request->get('record');
        if (!empty($recordId)) {
            $recordModel = Vtiger_Record_Model::getInstanceById($recordId, 'Contacts');

            // START: HARDCODE PORTAL USER SAVE LOGIC (Fix 8)
            $isPortalUser = $request->get('portal');
            
            // หากผู้ใช้ถูกตั้งค่าให้เป็น Portal User ให้บังคับสร้าง/อัปเดต Record
            if ($isPortalUser == 1 || $isPortalUser == 'Yes') {
                // โหลดไฟล์ Utility ที่ใช้ในการสร้าง Portal User
                // **จุดที่มักจะเกิด Fatal Error**
                try {
                    require_once 'modules/CustomerPortal/PortalUtils.php';
                    
                    $contactId = $recordModel->getId();
                    $contactEmail = $recordModel->get('email');
                    
                    // บังคับสร้าง/อัปเดต Record ในตาราง Portal
                    PortalUtils::createPortalUser($contactId, $contactEmail, $contactEmail, $contactEmail);
                    
                    // บังคับส่งอีเมลแจ้งรหัสผ่าน
                    PortalUtils::sendEmailNotification($contactId, $contactEmail); 

                    // ถ้ามาถึงบรรทัดนี้ได้ แสดงว่าโค้ดทำงาน
                    echo "PORTAL LOGIC SUCCESS: Portal User Record Created. Check Database/Email.";
                    
                } catch (Exception $e) {
                    // Catch Error ถ้ามี
                    die("PORTAL LOGIC FATAL ERROR: " . $e->getMessage() . ". Dependency Missing.");
                }
            }
            // END: HARDCODE PORTAL USER SAVE LOGIC
        }
	}
}
