<?php
// Set the admin email
$adminEmail = "admin@example.com";

// Sanitize and validate input
function sanitize_input($data) {
    return htmlspecialchars(stripslashes(trim($data)));
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Sanitize form data
    $firstName = sanitize_input($_POST['FirstName']);
    $middleName = sanitize_input($_POST['MiddleName']);
    $lastName = sanitize_input($_POST['LastName']);
    $parentName = sanitize_input($_POST['parentName']);
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    $gender = sanitize_input($_POST['gender']);
    $parentOccupation = sanitize_input($_POST['parentOccupation']);
    $address = sanitize_input($_POST['address']);
    $grade = sanitize_input($_POST['grade']);

    // Validate email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Invalid email format']);
        exit();
    }

    // Allowed MIME types
    $allowedImageTypes = ['image/jpeg', 'image/png', 'image/jpg'];
    $allowedDocTypes = [
        'application/pdf',
        'application/msword', // .doc
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' // .docx
    ];
    $allowedBirthCertTypes = array_merge($allowedImageTypes, $allowedDocTypes);
    $allowedSchoolResultTypes = array_merge($allowedImageTypes, $allowedDocTypes);

    // Validate file uploads
    $birthCertificate = $_FILES['birthCertificate'];
    $schoolResult = $_FILES['schoolResult'];
    $picture = $_FILES['picture'];

    // Validate 'picture' (only images)
    $pictureMimeType = mime_content_type($picture['tmp_name']);
    if (!in_array($pictureMimeType, $allowedImageTypes)) {
        echo json_encode(['success' => false, 'message' => 'Only JPEG, JPG, and PNG files are allowed for the picture.']);
        exit();
    }

    // Validate 'birthCertificate' (images or documents)
    $birthCertificateMimeType = mime_content_type($birthCertificate['tmp_name']);
    if (!in_array($birthCertificateMimeType, $allowedBirthCertTypes)) {
        echo json_encode(['success' => false, 'message' => 'Only JPEG, JPG, PNG, PDF, DOC, and DOCX files are allowed for the birth certificate.']);
        exit();
    }

    // Validate 'schoolResult' (images or documents)
    $schoolResultMimeType = mime_content_type($schoolResult['tmp_name']);
    if (!in_array($schoolResultMimeType, $allowedSchoolResultTypes)) {
        echo json_encode(['success' => false, 'message' => 'Only JPEG, JPG, PNG, PDF, DOC, and DOCX files are allowed for the school result.']);
        exit();
    }

    // Save uploaded files to a directory
    $targetDir = "uploads/";
    if (!file_exists($targetDir)) {
        mkdir($targetDir, 0777, true);
    }

    $picturePath = $targetDir . uniqid() . "_" . basename($picture["name"]);
    $birthCertPath = $targetDir . uniqid() . "_" . basename($birthCertificate["name"]);
    $schoolResultPath = $targetDir . uniqid() . "_" . basename($schoolResult["name"]);

    move_uploaded_file($picture["tmp_name"], $picturePath);
    move_uploaded_file($birthCertificate["tmp_name"], $birthCertPath);
    move_uploaded_file($schoolResult["tmp_name"], $schoolResultPath);

    // Email content to send to admin
    $subject = "New Admission Registration";
    $message = "
        <h3>New Admission Form Submitted</h3>
        <p><strong>Student Name:</strong> $firstName $middleName $lastName</p>
        <p><strong>Parent Name:</strong> $parentName</p>
        <p><strong>Email:</strong> $email</p>
        <p><strong>Gender:</strong> $gender</p>
        <p><strong>Parent Occupation:</strong> $parentOccupation</p>
        <p><strong>Address:</strong> $address</p>
        <p><strong>Grade Applying For:</strong> $grade</p>
    ";

    // Include the attachments in the email
    $boundary = md5(time());

    // Headers for attachment
    $headers = "From: no-reply@example.com\r\n";
    $headers .= "Reply-To: no-reply@example.com\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: multipart/mixed; boundary=\"$boundary\"\r\n";

    // Message Body
    $body = "--$boundary\r\n";
    $body .= "Content-Type: text/html; charset=UTF-8\r\n";
    $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
    $body .= chunk_split(base64_encode($message));

    // Attach files
    $attachments = [$picturePath, $birthCertPath, $schoolResultPath];
    foreach ($attachments as $filePath) {
        if (file_exists($filePath)) {
            $fileName = basename($filePath);
            $fileData = chunk_split(base64_encode(file_get_contents($filePath)));
            $body .= "--$boundary\r\n";
            $body .= "Content-Type: application/octet-stream; name=\"$fileName\"\r\n";
            $body .= "Content-Disposition: attachment; filename=\"$fileName\"\r\n";
            $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
            $body .= $fileData . "\r\n";
        }
    }
    $body .= "--$boundary--";

    // Send email to admin
    if (mail($adminEmail, $subject, $body, $headers)) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error sending email']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid Request']);
}
?>
