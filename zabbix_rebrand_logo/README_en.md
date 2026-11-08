# Zabbix Rebrand Logo

[中文](README.md)

## ✨ Version Compatibility

- ✅ Zabbix 7.0.32 / PHP 8.0
- ✅ Zabbix 8.0.0 / PHP 8.4

These combinations have been verified. Test other versions in your target environment first.

## Description

This Zabbix frontend module lets Super Admins change the login logo, sidebar logo, compact sidebar icon, and browser favicon under **Administration → Zabbix Rebrand Logo**. It also lets them set the server name, footer text, and Help URL. The module uses Zabbix's branding configuration without patching core files.

![Login page with a custom logo](docs/images/02-login-page.png)
![Sidebar with a custom logo](docs/images/03-sidebar.png)

## Features

- **Image preview and cropping**: Preview the selected image in place and use sliders to zoom and reposition it. Crops export as static PNG files.
- **Login logo size**: The width and height stay at a 4:1 ratio. The default is 120 × 30 pixels, and the maximum is 512 × 128 pixels, subject to server upload limits. Cropping can retain up to twice the display resolution when the source and file size allow it.
- **Server name**: Keep the Zabbix default, enter a custom name, or hide the name. The login page uses the Zabbix default.
- **Footer and Help link**: Customize the footer text and the Help link in the user menu. The link accepts HTTP and HTTPS URLs.
- **Chinese and English UI**: Follows the Zabbix user's language and supports light and dark themes.
- **Persistent storage**: Settings and image copies are stored in `local/conf/rebrand/` so they can be kept across upgrades.

| Image | Recommended size | Supported formats |
| --- | --- | --- |
| Compact sidebar icon | 24 × 24 pixels | PNG, JPG, GIF, ICO |
| Browser favicon | 32 × 32 pixels | PNG, JPG, GIF, ICO |
| Sidebar logo | 91 × 24 pixels | PNG, JPG, GIF |
| Login logo | 120 × 30 pixels | PNG, JPG, GIF |

## Installation

### Install the module

Run the following commands from the repository root. Set `ZABBIX_ROOT` to the directory serving the Zabbix frontend, such as `/usr/share/zabbix` or `/usr/share/zabbix/ui`. Set `WEB_USER` and `WEB_GROUP` to the PHP-FPM pool's user and group, such as `apache` or `www-data`.

```bash
ZABBIX_ROOT=/usr/share/zabbix
WEB_USER=apache
WEB_GROUP=apache

sudo cp -a zabbix_rebrand_logo "$ZABBIX_ROOT/modules/"
MODULE_DIR="$ZABBIX_ROOT/modules/zabbix_rebrand_logo"
CONF_DIR="$ZABBIX_ROOT/local/conf"

sudo install -d -o "$WEB_USER" -g "$WEB_GROUP" -m 0755 \
  "$MODULE_DIR/assets/logos" "$CONF_DIR/rebrand"
sudo chgrp "$WEB_GROUP" "$CONF_DIR"
sudo chmod g+w "$CONF_DIR"
```

PHP-FPM needs write access to the image directory, `local/conf/rebrand/`, and `local/conf/` to save images and atomically replace `brand.conf.php`. Keep the module code under the deployment account's ownership. On SELinux systems, configure the writable directories with `httpd_sys_rw_content_t` and apply the labels with `restorecon`.

### Enable the module

1. Sign in as a Super Admin and go to **Administration → General → Modules**.
2. Click **Scan directory** and enable **Zabbix Rebrand Logo**.
3. Open **Administration → Zabbix Rebrand Logo**, select and preview or crop your images, then click **Update**.

### Enable the login logo size slider (optional)

Install the local login view in the frontend root. Run the following command only if the destination is absent. If you already use a custom login view, check compatibility before enabling this option.

```bash
LOGIN_VIEW="$ZABBIX_ROOT/local/app/views/general.login.php"
if [ ! -e "$LOGIN_VIEW" ] && [ ! -L "$LOGIN_VIEW" ]; then
  sudo install -D -m 0644 "$MODULE_DIR/install/login-view.php" "$LOGIN_VIEW"
fi
```

### Enable the browser favicon (optional)

Upload and save the favicon in the module. Then back up the frontend's original `favicon.ico` and create a symlink to the module's favicon. Use the commands below when the frontend still has its original favicon and no backup. If you already use a custom symlink, check its target before updating it.

```bash
if [ -f "$MODULE_DIR/assets/logos/favicon.ico" ] \
  && [ -f "$ZABBIX_ROOT/favicon.ico" ] && [ ! -L "$ZABBIX_ROOT/favicon.ico" ] \
  && [ ! -e "$ZABBIX_ROOT/favicon.ico.zabbix-default" ]; then
  sudo mv "$ZABBIX_ROOT/favicon.ico" "$ZABBIX_ROOT/favicon.ico.zabbix-default"
  sudo ln -s "$MODULE_DIR/assets/logos/favicon.ico" "$ZABBIX_ROOT/favicon.ico"
fi
```

### Upgrade from the old directory

1. Back up the old module directory, `local/conf/rebrand/`, and `local/conf/brand.conf.php`. Disable the old module in the module list.
2. Move the old module directory (`zabbix_brand_logo`, `zabbix_branding`, or `Branding`) outside the frontend's `modules/` directory. Scan the directory to clear its registration.
3. Install `zabbix_rebrand_logo`, scan again, and enable it. If any legacy settings or images exist only in the old module's `assets/logos/` directory, copy them to the corresponding directory in the new module.
4. Open the module and click **Update** to rewrite the image URLs in `brand.conf.php` for the new directory. If you use a favicon symlink, update it to point to the new directory as well.

The module ID is `zabbix-rebrand-logo`. Persistent settings remain in `local/conf/rebrand/`.

## Notes

- Each file is limited to 2 MB, subject to PHP's upload limits. On save, a login image with pending crop changes or a file over the limit is exported as a PNG that fits the limit.
- To keep an animated GIF, upload the original within the file size limit and skip cropping. ICO is recommended for the browser favicon.
- If an old image still appears after updating it, try a hard refresh. A private browser window can help verify favicon changes.

## Development

- `manifest.json` and `Module.php`: Module declaration and menu registration.
- `actions/`: Reading, validating, and saving settings and images.
- `views/` and `assets/css/`: Settings interface, previews, and cropping.
- `install/login-view.php`: Optional login logo sizing integration.
- `lib/`: Language, size, and server name handling.

Run the server name regression test with PHP 8.1 or later on the command line:

```bash
php zabbix_rebrand_logo/tests/ServerNameOverrideTest.php
```

## Acknowledgements

Thanks to [Keberneth](https://github.com/Keberneth) for creating and sharing the original [Zabbix Branding module](https://github.com/Keberneth/Zabbix-Modules/tree/main/Branding), on which this module is based.
