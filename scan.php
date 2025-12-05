<?php

// Scan only websites that you own or have explicit permission to scan


header('Content-Type: text/plain');

# CLI
if (php_sapi_name() === 'cli') {
    foreach ($argv as $arg) {
        if (strpos($arg, '=') !== false) {
            list($key, $value) = explode('=', $arg, 2);
            $_GET[$key] = $value;
        }
    }
}

# URI
$domain = filter_var($_GET['domain'] ?? null, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME);

if (!$domain) die("Invalid domain.\n");
if (!checkdnsrr($domain, "A") && !checkdnsrr($domain, "AAAA")) { die("Domain does not resolve!\n");}


echo "Scanning: $domain\n\n";

$paths = ["", "backup", "backups", "site-backup", "old", "OLD", "bk", "db", "database", "wp-backup",
    "wp-content", "wp-content/uploads", "wp-content/uploads/backup", "wp-content/plugins",
    "wp-content/plugins/backup", "wp-content/themes", "wp-content/themes/backup",
    "wp-content/uploads/old", "wp-content/uploads/temp", "wp-content/uploads/backup-old",
    "wp-admin", "wp-admin/backup", "wp-includes", "wp-includes/backup", "tmp", "temp",
    "archive", "archives", "dump", "db_backups", "database_backups", "config", "configs",
    "conf", "old_site", "backup_site", "backups_site", "private"];

$filenames = [
    "wp-config.php.bak","wp-config.php2","wp-config.php.old","wp-config.php.save","wp-config.php~",
    "wp-config-backup.php","wp-config.php.txt","backup.sql","db.sql","database.sql","dump.sql",
    "wordpress.sql","site.sql","db.sql.gz","backup.sql.gz","dump.sql.gz","database.sql.gz",
    "wordpress.sql.gz","db_backup.sql","database_backup.sql","dump_backup.sql","db.sql.bak",
    "dump.sql.bak",".well-known.zip","backup.zip","site-backup.zip","wordpress.zip","backup.tar.gz",
    "backup.tgz","backup.tar","site.zip","site.tar.gz","files.zip","www.zip",
    "public_html.zip","wordpress-backup.zip","wp-backup.zip","wp-content-backup.zip",
    "uploads-backup.zip","themes-backup.zip","plugins-backup.zip",".litespeed_conf.dat",".htpasswd",
    ".env",".env.production",".htaccess",".htaccess.bk",".htaccess.bak",".htaccess.old",
    ".user.ini","error_log","debug.log","config.php","config.old.php","robots.txt","credentials.txt",
    "secrets.txt","private.key","id_rsa","id_rsa.pub","backup.key","database.key"
];

$commonBases = ["backup","db","dump","site","wordpress","config", "error_log"];
$extensions = ["sql","sql.gz","zip","tar", ".gz", "tar.gz","rar","bak","old","save","txt"];
foreach ($commonBases as $base) {
    foreach ($extensions as $ext) {
        $filenames[] = "$base.$ext";
        $filenames[] = "$base-YYYYMMDD.$ext";
    }
}

$urlFile = __DIR__ . "/urls_$domain.txt";
if (!file_exists($urlFile)) {
    echo "Generating URL list...\n";
    $urls = [];
    foreach ($paths as $path) {
        $prefix = $path ? "$path/" : "";
        foreach ($filenames as $file) {
            $urls[] = "https://$domain/$prefix$file";
        }
    }
    file_put_contents($urlFile, implode("\n", $urls));
} else {
    echo "Loading URL list from cache...\n";
    $urls = file($urlFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
}

$today = date("Ymd");
foreach ($urls as &$url) {
    $url = str_replace("YYYYMMDD", $today, $url);
}

$totalChecked = 0;
$totalFound = 0;
$startTime = microtime(true);
$concurrency = 100;
$chunks = array_chunk($urls, $concurrency);

foreach ($chunks as $chunk) {
    $multi = curl_multi_init();
    $handles = [];

    foreach ($chunk as $url) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_NOBODY => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT => 6,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT => "BackupScanner/1.0",
            CURLOPT_HTTPHEADER => ["Connection: keep-alive"]
        ]);
        curl_multi_add_handle($multi, $ch);
        $handles[(int)$ch] = ['handle' => $ch, 'url' => $url];
    }

    do {
        curl_multi_exec($multi, $running);

        while ($info = curl_multi_info_read($multi)) {
            $chInfo = $info['handle'];
            $url = $handles[(int)$chInfo]['url'];

            $totalChecked++;
            $code = curl_getinfo($chInfo, CURLINFO_HTTP_CODE);
            $size = curl_getinfo($chInfo, CURLINFO_CONTENT_LENGTH_DOWNLOAD);

            if ($code == 200 && $size > 200) {
                $totalFound++;
                echo "[FOUND] $url ($size bytes)\n";
                
                               
                if (!is_dir($domain)) {
                    mkdir($domain, 0777, true);
                }
                
                $timestamp = date("Ymd");
                $folder = $domain . DIRECTORY_SEPARATOR . $timestamp;
                if (!is_dir($folder)) {
                    mkdir($folder, 0777, true);
                }
                
                $filename = basename($url);
                
                $filePath = $folder . DIRECTORY_SEPARATOR . $filename;
                file_put_contents($filePath, file_get_contents($url));
            } else {
                echo "[--] $url\n";
            }

            @ob_flush(); @flush();

            curl_multi_remove_handle($multi, $chInfo);
            curl_close($chInfo);
            unset($handles[(int)$chInfo]);
        }

        usleep(500); // 0.5ms
    } while ($running > 0);

    curl_multi_close($multi);
}

$endTime = microtime(true);
$duration = round($endTime - $startTime, 2);

echo "\nScan completed.\n";
echo "----------------------------------------------------------\n";
echo "Total checked: $totalChecked\n";
echo "Files found:   $totalFound\n";
echo "Time taken:    $duration seconds\n";
echo "----------------------------------------------------------\n";

?>
