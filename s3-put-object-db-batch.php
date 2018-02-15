<?php
require 'C:\Users\admin\bin\vendor\autoload.php';
use Aws\S3\S3Client;
use Aws\Exception\AwsException;

// Hardcoded credentials for Amazon S3
$KEY='';
$SECRET='';

$goodSQLServer = FALSE;
$goodAmazonS3 = FALSE;

$s3Bucket = "devcroproviderupload";
$providerID = 0;                // TODO: make parameter
$s3ProviderFolder='smscorp';    // TODO: make parameter

// 1.Connect to SQL Server
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

// 2.Connect to Amazon S3
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

//
// 3.Transfer images from SQL Server tblImage
//
// 3.1.Creating objects with the following folder/object ID structure:
//
//     ProviderName/ImageID-TableName-ObjectID
//
// 3.2.TODO: Updated SQL Server tblImage with the ID
//
if($goodSQLServer && $goodAmazonS3){

    $tsql = "SELECT ImageID,ImageData,TableName,ObjectID FROM tblImage";

    $stmt = sqlsrv_query( $conn, $tsql);  
    if( $stmt === false){  
        echo "Error in query preparation/execution.\n";  
        die( print_r( sqlsrv_errors(), true));  
    }  

    $sql2 = "UPDATE tblImage
        SET s3object = ?
        WHERE ImageID = ?";

    while( $row = sqlsrv_fetch_array( $stmt, SQLSRV_FETCH_ASSOC)){  
     
        $imageID = $row['ImageID'];
        $s3ObjectName = 
            $imageID."-".
            $row['TableName']."-".
            $row['ObjectID'];
        
        $s3FolderObject = 
            $s3ProviderFolder."/".$s3ObjectName;
           
        $s3ObjectData = $row['ImageData'];

        // Put data in s3 bucket as a folder/object
        try{
            $result = $s3Client->putObject([
                'Bucket'    => $s3Bucket,
                'Key'       => $s3FolderObject,
                'Body'      => $s3ObjectData
            ]);    
           
            $msg = 
                'Successful S3 Put -------------------------------------------------------------<br>
                Bucket : '.$s3Bucket.'<br>
                Folder : '.$s3ProviderFolder.'<br>
                Object : '.$s3ObjectName.'<br>';
            
            
            echo $msg;
        
        } 
        catch (S3Exception $e) {
            echo $e->getMessage() . "<br>";
            die();
        } 


        
        $stmt2 = sqlsrv_prepare( $conn, $sql2, array( $s3FolderObject, $imageID));
        if( !$stmt2 ) {
            die( print_r( sqlsrv_errors(), true));
        }
        if( sqlsrv_execute( $stmt2 ) === false ) {
            die( print_r( sqlsrv_errors(), true));
        }
      
    }  
    sqlsrv_free_stmt( $stmt2);
    
    sqlsrv_free_stmt( $stmt); 

   sqlsrv_close( $conn);  
}



?>