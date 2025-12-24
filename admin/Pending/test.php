// Test single message
$testPhone = "91XXXXXXXXXX"; // Replace with a test phone number
$testMessage = "Test message";
$postData = [
    'phone' => $testPhone,
    'message' => $testMessage,
    'instance_id' => $whatsappConfig['InstanceID'],
    'token' => $whatsappConfig['Token']
];
// ... rest of the sending code