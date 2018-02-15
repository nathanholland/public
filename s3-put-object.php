<?php
 
require 'C:\Users\admin\bin\vendor\autoload.php';
use Aws\S3\S3Client;
use Aws\Exception\AwsException;

// TODO: remove hardcoded credentials for Amazon S3
$KEY='';
$SECRET='';

$s3Bucket = "devcroproviderupload"; // TODO: ?
$s3ProviderFolder='smscorp';    // TODO: make param
$objectID = 1571;   // TODO: make param
$tableName = 'tblBinRequest';     // TODO: make param

$goodUpload = FALSE;
$goodSQLServer = FALSE;
$goodAmazonS3 = FALSE;
$goodS3Put = FALSE;

$sourcePathFile = '';

// Verify file upload
if (isset($_FILES["file"]["name"])) {

    $uploadFileName = $_FILES["file"]["name"];
    $sourcePathFile = $_FILES['file']['tmp_name'];
    $error = $_FILES['file']['error'];

    if (!empty($uploadFileName)) {
        echo 'uploaded '.$uploadFileName.' to server as '.$sourcePathFile.'<br><br>';
        $goodUpload = TRUE;
    } 
    else {
        echo 'please choose a file';
        die();
    }
}

// Verify SQL Server
$serverName = "1844crobins.info,1433";
$connectionInfo = array(
    "Database"  =>"smscorp", 
    "UID"       => $user, 
    "PWD"       => $password);

$conn = sqlsrv_connect( $serverName, $connectionInfo);
if( $conn ) {
     echo "SQL Server Connection established.<br />";
    $goodSQLServer = TRUE;
}
else{
     echo "SQL Server Connection could not be established.<br />";
     die( print_r( sqlsrv_errors(), true));
}

// Verify Amazon S3
try{
    // Create a S3Client
    $s3Client = new S3Client([
        'region' => 'us-west-2',
        'version' => '2006-03-01',
        'credentials' => [
            'key'    => $KEY,
            'secret' => $SECRET
        ]
    ]);
    echo "Amazon S3 authentication successful for ".$KEY.".<br>";
    $goodAmazonS3 = TRUE;
}
catch (AwsException $e) {
    // output error message if fails
    echo "Amazon S3 authentication failed:<br>";
    echo $e->getMessage();
    die();    
}


if ($goodUpload && $goodSQLServer && $goodAmazonS3)
{
    // Add to tblImage
    $insertSQL = "INSERT INTO tblImage (TableName, ObjectID) VALUES(?,?)"; 
    $insertSQL .= "; SELECT SCOPE_IDENTITY() AS IDENTITY_COLUMN_NAME"; 

    //echo $insertSQL;
    
    $stmt = sqlsrv_query($conn, $insertSQL, array($tableName, $objectID));
    if( $stmt === false ){
        echo "Could insert or retrieve last inserted row ID.</br>";
        die( print_r( sqlsrv_errors(), true));
    } 

    $newImageID = lastInsertId($stmt); 
    sqlsrv_free_stmt( $stmt); 
    
    // Put file (object) in bucket
    $s3ObjectName = $newImageID."-".$tableName."-".$objectID;

    $s3FolderObject = 
        $s3ProviderFolder."/".$s3ObjectName;
    
    try{
        $result = $s3Client->putObject([
            'Bucket'     => $s3Bucket,
            'Key'        => $s3FolderObject,
            'SourceFile' => $sourcePathFile,
        ]);    
        echo 'successfully posted to S3<br><br>';
        $goodS3Put = TRUE;
    
    } 
    catch (S3Exception $e) {
        echo $e->getMessage() . "<br>";
    }  
    
    if($goodS3Put){

        $updateSQL = "UPDATE tblImage
            SET s3object = ?
            WHERE ImageID = ?";

        $stmt2 = sqlsrv_prepare( $conn, $updateSQL, array($s3FolderObject, $newImageID));
        if( !$stmt2 ) {
            die( print_r( sqlsrv_errors(), true));
        }
        if( sqlsrv_execute( $stmt2 ) === false ) {
            die( print_r( sqlsrv_errors(), true));
        }
        sqlsrv_free_stmt($stmt2);

        echo "tblImage row ID ".$newImageID." Column s3object ".$s3FolderObject;

    }
    
    sqlsrv_close( $conn);  
    
}

function lastInsertId($queryID) {
    sqlsrv_next_result($queryID);
    sqlsrv_fetch($queryID);
    return sqlsrv_get_field($queryID, 0);
} 



?>