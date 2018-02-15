<?php


require 'C:\Users\admin\bin\vendor\autoload.php';
use Aws\S3\S3Client;
use Aws\Exception\AwsException;


// Hardcoded credentials for Amazon S3
$KEY='';
$SECRET='';



$s3_bucket = "devcroproviderupload";


$s3_provider_folder='smscorp';    // TODO: make parameter

$s3_folder_object_key='smscorp/2062-tblBinRequest-1571';

//$s3FolderObjectKey = 'smscorp/24-tblBinRequest-1571';

// Create a S3Client
$s3 = new S3Client([
    'region' => 'us-west-2',
    'version' => '2006-03-01',
    'credentials' => [
        'key'    => $KEY,
        'secret' => $SECRET
    ]
]);

// Register the stream wrapper from an S3Client object
$s3->registerStreamWrapper();
try{
    
    
    
    $data = file_get_contents('s3://'.$s3_bucket.'/'.$s3_folder_object_key);

    //$data = base64_decode($data);
    header("Content-type: image/jpeg");
    echo $data;
} 
catch (\Exception $e) {
   echo  $e->getMessage();
}





?>