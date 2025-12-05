# Backup & Sensitive File Scanner (PHP)

Scan a target domain for publicly accessible backup files, database dumps, configuration files, and other potentially sensitive resources commonly leaked by mistake.

Example results: [pcelarstvopejcic.com](https://github.com/stefanpejcic/wp-backup-scanner/actions/runs/19971581926/job/57277411236#step:5:17)

<a href="https://github.com/new?template_name=wp-backup-scanner&template_owner=stefanpejcic">
  <img src="https://img.shields.io/badge/Use%20this%20template-2ea44f?style=for-the-badge" alt="Use this template">
</a>

---
> ⚠️ Use only on systems you own or have explicit permission to test. Unauthorized scanning is illegal.
---


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
