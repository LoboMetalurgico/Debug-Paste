<?php
if ($_SERVER['REQUEST_METHOD'] != 'PUT') {
    header("HTTP/1.1 405 Method Not Allowed");
    header("Allow: PUT");
    exit;
}

if (@$_SERVER['HTTP_CONTENT_TYPE'] != "application/zip") {
    header("HTTP/1.1 415 Unsupported Media Type");
    exit;
}

$len = @$_SERVER['HTTP_CONTENT_LENGTH'];
if (!$len || $len <= 0) {
    header("HTTP/1.1 411 Length Required");
    exit;
}

if ($len > 15*1024*1024) {
    header("HTTP/1.1 413 Payload Too Large");
    exit;
}

$tmp_dir = sys_get_temp_dir();
$disk_free_space = disk_free_space($tmp_dir);
if (!$disk_free_space || $disk_free_space < $len * 40) {
    header("HTTP/1.1 507 Insufficient Storage");
    exit(1);
}

$input = @fopen("php://input", "r");
if (!$input) {
    header("HTTP/1.1 400 Bad Request");
    exit;
}

$tmp_dir = tempnam($tmp_dir, "debugpaste_upload");
$output = fopen("$tmp_dir/upload.zip", "w");
if (!$output) {
    @fclose($input);
    header("HTTP/1.1 500 Internal Server Error");
    error_log("Failed to open the file for writing: $tmp_dir/upload.zip");
    exit(2);
}

$total = 0;
while ($data = fread($input,1024)) {
    $wrote = fwrite($output, $data);
    if (!$wrote || $wrote < 0) {
        header("HTTP/1.1 500 Internal Server Error");
        error_log("Failed to write all bytes. Wrote $total or $len");
        abort();
        exit(3);
    }
    
    $total += $wrote;
    if ($total > $len) {
        header("HTTP/1.1 413 Payload Too Large");
        abort();
        exit(4);
    }
}

abort();

echo "https://debugpaste.powernukkit.org/testing\n";
echo $total;

function abort() {
    global $input, $output, $tmp_dir;
    
    @fclose($input);
    @fclose($output);
    @unlink($output);
    
    delTree($tmp_dir);
}

function delTree($dir) {
    $files = array_diff(scandir($dir), array('.','..'));
    foreach ($files as $file) {
        (is_dir("$dir/$file")) ? delTree("$dir/$file") : unlink("$dir/$file");
    }
    return rmdir($dir);
}
