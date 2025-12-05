# Backup & Sensitive File Scanner (PHP)

This tool scans a target domain for publicly accessible backup files, database dumps, configuration files, and other potentially sensitive resources commonly leaked by mistake.
It builds a large list of likely file paths, checks them in parallel, and automatically downloads anything it finds.

> ⚠️ Use only on systems you own or have explicit permission to test. Unauthorized scanning is illegal.


## Usage

Run the script via web or CLI by providing a `domain` parameter:

```bash
php scan.php domain=example.com
```

or via browser:

```bash
http://localhost/scan.php?domain=example.com
```

If files are detected, they will be stored in: `./<domain>/<YYYYMMDD>/<filename>`

