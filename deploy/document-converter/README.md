# MBG Document Converter

Layanan ini dijalankan pada unprivileged CT terpisah. API hanya boleh diakses dari IP Web CT melalui firewall Proxmox atau UFW.

## Instalasi Ubuntu

```bash
sudo apt update
sudo apt install -y libreoffice-writer python3-venv fonts-liberation
sudo useradd --system --home /opt/mbg-document-converter --shell /usr/sbin/nologin docconverter
sudo mkdir -p /opt/mbg-document-converter
sudo cp app.py requirements.txt /opt/mbg-document-converter/
sudo chown -R docconverter:docconverter /opt/mbg-document-converter
sudo -u docconverter python3 -m venv /opt/mbg-document-converter/venv
sudo -u docconverter /opt/mbg-document-converter/venv/bin/pip install --requirement /opt/mbg-document-converter/requirements.txt
sudo cp document-converter.env.example /etc/mbg-document-converter.env
sudo chmod 600 /etc/mbg-document-converter.env
sudo cp document-converter.service /etc/systemd/system/document-converter.service
```

Buat token:

```bash
openssl rand -hex 32
```

Masukkan token tersebut ke `/etc/mbg-document-converter.env` dan menu admin **Template surat**. Kemudian:

```bash
sudo systemctl daemon-reload
sudo systemctl enable --now document-converter
sudo systemctl status document-converter
```

Batasi port agar hanya Web CT yang dapat mengakses. Contoh jika IP Web CT adalah `192.168.0.252`:

```bash
sudo ufw allow from 192.168.0.252 to any port 3000 proto tcp
sudo ufw deny 3000/tcp
sudo ufw enable
```

Jangan membuka port `3000` ke internet. Gunakan tab **Uji koneksi** di admin setelah firewall dan token selesai dikonfigurasi.

Web CT membutuhkan ekstensi Imagick agar placeholder `${qr_code}` dapat diisi sebagai PNG:

```bash
sudo apt install -y php-imagick
sudo systemctl restart php8.3-fpm
```
