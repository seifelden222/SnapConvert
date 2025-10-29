# 🎨 SnapConvert

> A powerful web-based image processing suite for format conversion, compression, and QR code generation

[![PHP Version](https://img.shields.io/badge/PHP-8.4%2B-blue.svg)](https://www.php.net/)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)
[![Intervention Image](https://img.shields.io/badge/Intervention%20Image-v3-orange.svg)](https://image.intervention.io/)

## ✨ Features

### 🔄 Image Format Converter
Convert images between all major formats with high quality:
- **Supported Formats**: PNG, JPEG, WEBP, AVIF, GIF, SVG
- **36 Conversion Combinations**: Any format to any format
- **SVG Support**: 
  - SVG → Raster (PNG, JPEG, WEBP, AVIF, GIF)
  - Raster → SVG (vectorization via Potrace)
- **High Quality Output**: 95% quality by default for lossy formats
- **In-Memory Processing**: No files stored on server
- **Instant Download**: Base64 data URI for immediate download

### 🗜️ Image Compression
Reduce image file sizes while maintaining quality:
- **Quality Control**: Adjustable slider (1-100%)
- **Supported Formats**: JPEG, PNG, WEBP, AVIF, GIF
- **Real-Time Stats**: 
  - Original size vs Compressed size
  - Reduction percentage
  - Visual comparison
- **Smart Compression**: 
  - AVIF: Best compression (up to 77% reduction)
  - WEBP: Great balance (40-50% reduction)
  - JPEG: Standard compression

### 📱 QR Code Generator
Create customizable QR codes:
- **Custom Colors**: Foreground and background color picker
- **Text Input**: Any text or URL
- **High Resolution**: 300x300px output
- **Instant Preview**: Real-time generation
- **Download Ready**: PNG format

## 🚀 Quick Start

### Prerequisites
- PHP 8.4 or higher
- Composer
- Imagick extension (with librsvg support for SVG)
- GD extension (fallback)
- Potrace (for raster to SVG conversion)

### Installation

1. **Clone the repository**
```bash
git clone https://github.com/seifelden222/SnapConvert.git
cd SnapConvert
```

2. **Install dependencies**
```bash
composer install
```

3. **Install system dependencies**

For **Ubuntu/Debian**:
```bash
sudo apt-get update
sudo apt-get install -y php-imagick php-gd librsvg2-bin potrace
```

For **macOS** (using Homebrew):
```bash
brew install imagemagick librsvg potrace
pecl install imagick
```

For **Arch Linux**:
```bash
sudo pacman -S php-imagick php-gd librsvg potrace
```

4. **Configure PHP**

Ensure these extensions are enabled in your `php.ini`:
```ini
extension=imagick
extension=gd
```

5. **Start the development server**
```bash
php -S localhost:8000
```

6. **Open in browser**
```
http://localhost:8000
```

## 📁 Project Structure

```
SnapConvert/
├── vendor/                 # Composer dependencies
│   ├── intervention/image  # Image processing library
│   └── endroid/qr-code    # QR code generation
├── include/
│   ├── DB/
│   │   └── db.php         # Database configuration
│   ├── temb/
│   │   ├── header.php     # HTML head & meta tags
│   │   ├── navbar.php     # Navigation bar
│   │   └── footer.php     # Footer
│   └── assest/
│       └── css/
│           └── sytle.css  # Custom styles
├── Convert_images.php      # Image format converter
├── Compress_images.php     # Image compression tool
├── QR_cood.php            # QR code generator
├── composer.json          # PHP dependencies
└── README.md              # This file
```

## 🛠️ Technology Stack

### Backend
- **PHP 8.4**: Modern PHP features and performance
- **Intervention Image v3**: Powerful image manipulation
- **Imagick**: Advanced image processing (preferred driver)
- **GD**: Fallback image processing
- **Endroid QR Code v6**: QR code generation

### Frontend
- **Bootstrap 5**: Responsive UI framework
- **Bootstrap Icons**: Icon library
- **Vanilla JavaScript**: Interactive features

### Image Processing
- **librsvg**: SVG rasterization
- **Potrace**: Bitmap to vector tracing
- **Imagick**: ImageMagick PHP extension

## 📊 Conversion Matrix

All 36 format combinations are supported:

| FROM → TO | PNG | JPEG | WEBP | AVIF | GIF | SVG |
|-----------|:---:|:----:|:----:|:----:|:---:|:---:|
| **PNG**   | ✅  | ✅   | ✅   | ✅   | ✅  | ✅  |
| **JPEG**  | ✅  | ✅   | ✅   | ✅   | ✅  | ✅  |
| **WEBP**  | ✅  | ✅   | ✅   | ✅   | ✅  | ✅  |
| **AVIF**  | ✅  | ✅   | ✅   | ✅   | ✅  | ✅  |
| **GIF**   | ✅  | ✅   | ✅   | ✅   | ✅  | ✅  |
| **SVG**   | ✅  | ✅   | ✅   | ✅   | ✅  | ✅  |

**Testing Results**: 36/36 conversions successful (100% success rate)

## 🔧 Configuration

### File Upload Limits
- **Converter**: 8 MB maximum
- **Compressor**: 10 MB maximum

### Image Quality
- **Default Quality**: 75% (compression)
- **Conversion Quality**: 95% (best quality)
- **SVG Resolution**: 300 DPI

### Allowed Formats
```php
// Converter
$allowed_exts = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'svg'];

// Compressor
$allowed_exts = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif'];
```

## 🔒 Security Features

- **File Extension Validation**: Whitelist-based checking
- **MIME Type Verification**: Using `finfo_file()`
- **Real Image Verification**: Using `getimagesize()`
- **File Size Limits**: Configurable maximum upload size
- **Sanitized Filenames**: Remove special characters
- **No Server Storage**: All processing in-memory
- **XSS Protection**: HTML entity encoding for output

## 🎯 Use Cases

- **Web Developers**: Convert images for optimal web performance
- **Designers**: Compress images without quality loss
- **Marketing**: Generate QR codes for campaigns
- **Content Creators**: Optimize images for different platforms
- **Mobile Apps**: Convert and compress images for mobile use

## 📈 Performance

### Compression Results (Example: 15.74 KB PNG)

| Format | Quality | Output Size | Reduction |
|--------|---------|-------------|-----------|
| AVIF   | 25%     | 3.53 KB     | **77.6%** |
| GIF    | Default | 3.31 KB     | **78.9%** |
| WEBP   | 75%     | 9.2 KB      | **41.6%** |
| JPEG   | 75%     | 10.32 KB    | **34.4%** |

## 🐛 Troubleshooting

### SVG Conversion Not Working
```bash
# Check Imagick SVG support
php -r "echo in_array('SVG', (new Imagick())->queryFormats('SVG*')) ? 'YES' : 'NO';"

# If NO, install librsvg
sudo apt-get install librsvg2-bin librsvg2-dev
```

### Raster to SVG Not Working
```bash
# Check if Potrace is installed
command -v potrace

# If not found, install it
sudo apt-get install potrace
```

### Memory Limit Issues
Edit `php.ini`:
```ini
memory_limit = 256M
upload_max_filesize = 10M
post_max_size = 10M
```

## 🤝 Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit your changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to the branch (`git push origin feature/AmazingFeature`)
5. Open a Pull Request

## 📝 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 👨‍💻 Author

**Seif Eldin Hamdy**
- GitHub: [@seifelden222](https://github.com/seifelden222)
- Repository: [SnapConvert](https://github.com/seifelden222/SnapConvert)

## 🙏 Acknowledgments

- [Intervention Image](https://image.intervention.io/) - PHP image manipulation library
- [Endroid QR Code](https://github.com/endroid/qr-code) - QR code generation
- [Bootstrap](https://getbootstrap.com/) - UI framework
- [Potrace](http://potrace.sourceforge.net/) - Bitmap tracing
- [ImageMagick](https://imagemagick.org/) - Image processing suite

## 📞 Support

If you have any questions or need help, please:
- Open an issue on [GitHub Issues](https://github.com/seifelden222/SnapConvert/issues)
- Check the [Troubleshooting](#-troubleshooting) section

---

⭐ **If you find this project useful, please give it a star!** ⭐