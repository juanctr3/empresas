<?php
// Mocking the behavior for verification
$test_settings = json_decode('{}', true); // This becomes an empty array []
if (is_array($test_settings) && empty($test_settings)) {
    echo "Confirmed: json_decode('{}', true) is an empty array.\n";
}

$encoded_array = json_encode($test_settings);
echo "Encoded empty array: $encoded_array\n"; // Gives "[]"

$encoded_object = json_encode((object)$test_settings);
echo "Encoded cast object: $encoded_object\n"; // Gives "{}"

// Validation of the fix logic
$f_settings = []; // What we might get from input if empty
$saved_json = json_encode($f_settings ?: (object)[]);
echo "Saved JSON with fix: $saved_json\n";
?>
