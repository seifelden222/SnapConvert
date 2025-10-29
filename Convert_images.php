<?php
require 'vendor/autoload.php';

use Intervention\Image\ImageManager;

include("include/DB/db.php");
include("include/temb/header.php");

/* -------------------- Config -------------------- */
$allowed_exts  = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'svg'];
$allowed_mimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/avif', 'image/svg+xml'];
$maxBytes      = 8 * 1024 * 1024; // 8MB

$err = [];
$ok  = null;
$dataUri = null;      // Store the final data URI here
$downloadName = null; // Suggested filename for download

/* -------------------- Helper Functions -------------------- */

/**
 * Convert SVG to raster format using rsvg-convert (fallback method)
 * @param string $svgPath Path to SVG file
 * @param string $format Output format (png, jpeg, webp)
 * @param int $width Target width in pixels
 * @return array [bytes, mimeType]
 * @throws RuntimeException
 */
function svg_to_raster_via_rsvg(string $svgPath, string $format = 'png', int $width = 800): array {
    if (!function_exists('shell_exec')) {
        throw new RuntimeException('shell_exec is disabled; rsvg-convert cannot run.');
    }
    
    $fmt = in_array($format, ['png', 'jpeg', 'jpg', 'webp']) ? $format : 'png';
    $cmd = 'rsvg-convert -w ' . escapeshellarg((string)$width)
         . ' -f ' . escapeshellarg($fmt)
         . ' ' . escapeshellarg($svgPath) . ' 2>&1';
    
    $bytes = shell_exec($cmd);
    if (!$bytes) {
        throw new RuntimeException('rsvg-convert failed or returned empty output.');
    }
    
    $mime = ($fmt === 'jpg' || $fmt === 'jpeg') ? 'image/jpeg' : 'image/' . $fmt;
    return [$bytes, $mime];
}

/* -------------------- Handle POST -------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!isset($_FILES['image']) || !is_array($_FILES['image'])) {
        $err['image'] = "Please upload an image.";
    } else {
        $image = $_FILES['image'];

        if ($image['error'] !== UPLOAD_ERR_OK) {
            $err['image'] = "Upload error code: " . $image['error'];
        } else {

            if ($image['size'] > $maxBytes) {
                $err['size'] = "Max file size is " . (int)round($maxBytes / 1024 / 1024) . "MB.";
            }

            $ext = strtolower(pathinfo($image['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, $allowed_exts, true)) {
                $err['ext'] = "Allowed extensions: " . implode(', ', $allowed_exts);
            }

            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime  = finfo_file($finfo, $image['tmp_name']);
            finfo_close($finfo);

            if (!in_array($mime, $allowed_mimes, true)) {
                $err['mime'] = "Invalid MIME type: $mime";
            }

            // Check if it's a real image (only for basic raster formats)
            // Note: getimagesize may not support WEBP/AVIF on older PHP versions
            $rasterCheckMimes = ['image/jpeg', 'image/png', 'image/gif'];
            if (in_array($mime, $rasterCheckMimes, true) && @getimagesize($image['tmp_name']) === false) {
                $err['real'] = "The uploaded file is not a real image.";
            }

            // Output format (from <select>)
            $allowed_out = ['webp', 'avif', 'jpeg', 'png', 'gif', 'svg'];
            $outFormat   = isset($_POST['format']) ? strtolower(trim($_POST['format'])) : 'webp';
            if (!in_array($outFormat, $allowed_out, true)) {
                $err['format'] = "Unsupported output format.";
            }

            if (empty($err)) {
                $ok = "Checks passed. Ready to convert.";

                $baseName = preg_replace('/[^A-Za-z0-9_\-]+/', '_', pathinfo($image['name'], PATHINFO_FILENAME)) ?: 'image';
                $downloadName = $baseName . '.' . $outFormat;

                // اختار درايفر Intervention v3 (imagick لو متاح، غير كده gd)
                $driver  = extension_loaded('imagick') ? 'imagick' : 'gd';
                $manager = ($driver === 'imagick') ? ImageManager::imagick() : ImageManager::gd();

                // Helper لتحويل بولة بايتس إلى data URI
                $toDataUri = function (string $bytes, string $mime) {
                    return 'data:' . $mime . ';base64,' . base64_encode($bytes);
                };

                try {
                    if ($mime === 'image/svg+xml') {
                        // --- SVG input ---
                        if ($outFormat === 'svg') {
                            // SVG -> SVG (no conversion): display content as is
                            $bytes = file_get_contents($image['tmp_name']);
                            if ($bytes === false) {
                                throw new RuntimeException('Failed to read SVG data.');
                            }
                            $dataUri = $toDataUri($bytes, 'image/svg+xml');
                            $ok = "SVG uploaded successfully (no conversion).";
                        } else {
                            // SVG -> Raster conversion
                            
                            // Try Imagick first (best quality)
                            if (extension_loaded('imagick')) {
                                try {
                                    // Check if Imagick supports SVG
                                    $formats = Imagick::queryFormats('SVG');
                                    if (empty($formats)) {
                                        throw new RuntimeException('Imagick does not support SVG (librsvg missing)');
                                    }
                                    
                                    // Use Imagick directly for better control
                                    $imagick = new Imagick();
                                    
                                    // Set resolution BEFORE reading for better quality
                                    $imagick->setResolution(300, 300);
                                    
                                    // Read the SVG file
                                    $svgContent = file_get_contents($image['tmp_name']);
                                    $imagick->readImageBlob($svgContent);
                                    
                                    // Flatten layers for JPEG (removes transparency)
                                    if ($outFormat === 'jpeg') {
                                        $imagick->setImageBackgroundColor('white');
                                        $imagick->setImageAlphaChannel(Imagick::ALPHACHANNEL_REMOVE);
                                        $imagick = $imagick->mergeImageLayers(Imagick::LAYERMETHOD_FLATTEN);
                                    }
                                    
                                    // Get dimensions
                                    $width = $imagick->getImageWidth();
                                    $height = $imagick->getImageHeight();
                                    
                                    // Scale to reasonable size if too large
                                    $maxSize = 1600;
                                    if ($width > $maxSize || $height > $maxSize) {
                                        if ($width > $height) {
                                            $newWidth = $maxSize;
                                            $newHeight = (int)(($maxSize / $width) * $height);
                                        } else {
                                            $newHeight = $maxSize;
                                            $newWidth = (int)(($maxSize / $height) * $width);
                                        }
                                        $imagick->resizeImage($newWidth, $newHeight, Imagick::FILTER_LANCZOS, 1);
                                    }
                                    
                                    // Set output format
                                    $imagick->setImageFormat($outFormat === 'jpeg' ? 'jpg' : $outFormat);
                                    
                                    // Set compression quality for lossy formats
                                    if (in_array($outFormat, ['jpeg', 'webp', 'avif'], true)) {
                                        $imagick->setImageCompressionQuality(95);
                                    }
                                    
                                    // Get the binary data
                                    $encoded = $imagick->getImageBlob();
                                    
                                    // Clean up
                                    $imagick->clear();
                                    $imagick->destroy();
                                    
                                    $mimeType = $outFormat === 'jpeg' ? 'image/jpeg' : 'image/' . $outFormat;
                                    $dataUri = $toDataUri($encoded, $mimeType);
                                    $ok = "SVG converted successfully to " . strtoupper($outFormat) . " (via Imagick).";
                                    
                                } catch (\Exception $e) {
                                    // Imagick failed, try fallback
                                    throw new RuntimeException("Imagick SVG conversion failed: " . $e->getMessage());
                                }
                            } else {
                                // Fallback: Try rsvg-convert
                                try {
                                    [$encoded, $mimeType] = svg_to_raster_via_rsvg($image['tmp_name'], $outFormat, 1600);
                                    $dataUri = $toDataUri($encoded, $mimeType);
                                    $ok = "SVG converted successfully to " . strtoupper($outFormat) . " (via rsvg-convert).";
                                } catch (RuntimeException $e) {
                                    throw new RuntimeException("SVG conversion failed. Install Imagick with librsvg support or rsvg-convert: " . $e->getMessage());
                                }
                            }
                        }

                    } else {
                        // --- Raster input (JPEG/PNG/GIF/WebP/AVIF...) ---

                        if ($outFormat === 'svg') {
                            // Raster -> SVG (vectorization) عبر potrace (محتاج ملفات مؤقتة في الـ temp وهنمسحها فورًا)
                            $whichPotrace = shell_exec('command -v potrace 2>&1');
                            if (empty($whichPotrace)) {
                                throw new RuntimeException("Potrace is not installed. Install it to convert raster images to SVG.");
                            }

                            $img = $manager->read($image['tmp_name']);

                            // نحول مؤقتًا لـ BMP لأن potrace بيقبل BMP
                            $tmpDir = sys_get_temp_dir();
                            $uniq   = bin2hex(random_bytes(4));
                            $tmpBmp = $tmpDir . "/tmp_{$uniq}.bmp";
                            $tmpSvg = $tmpDir . "/tmp_{$uniq}.svg";

                            // حفظ BMP مؤقت (هيتخزن ثواني ويتشال)
                            $img->save($tmpBmp);

                            // Run potrace
                            $cmd = "potrace " . escapeshellarg($tmpBmp) . " -s -o " . escapeshellarg($tmpSvg) . " 2>&1";
                            $output = shell_exec($cmd);

                            // اقرأ الناتج وخليه data URI
                            if (!file_exists($tmpSvg) || filesize($tmpSvg) === 0) {
                                // نظّف قبل ما ترمي خطأ
                                if (file_exists($tmpBmp)) unlink($tmpBmp);
                                if (file_exists($tmpSvg)) unlink($tmpSvg);
                                throw new RuntimeException("SVG conversion failed. Output: " . (string)$output);
                            }

                            $bytes = file_get_contents($tmpSvg);
                            $dataUri = $toDataUri($bytes, 'image/svg+xml');

                            // Cleanup
                            if (file_exists($tmpBmp)) unlink($tmpBmp);
                            if (file_exists($tmpSvg)) unlink($tmpSvg);

                            $ok = "Image converted successfully to SVG (vectorized).";

                        } else {
                            // Raster -> Raster (كلو في الذاكرة)
                            $img = $manager->read($image['tmp_name']);

                            // (اختياري) تصغير الصور الكبيرة
                            // if ($img->width() > 1600) {
                            //     $img->resize(1600, null, function($c){ $c->aspectRatio(); $c->upsize(); });
                            // }

                            $mimeType = 'image/' . $outFormat;
                            if ($outFormat === 'jpeg') {
                                $mimeType = 'image/jpeg';
                            }
                            
                            switch ($outFormat) {
                                case 'jpeg':
                                    $encoded = $img->toJpeg(95); break;
                                case 'png':
                                    $encoded = $img->toPng(); break;
                                case 'webp':
                                    $encoded = method_exists($img, 'toWebp') ? $img->toWebp(95) : $img->encodeByExtension('webp', 95); break;
                                case 'avif':
                                    $encoded = method_exists($img, 'toAvif') ? $img->toAvif(95) : $img->encodeByExtension('avif', 95); break;
                                case 'gif':
                                    $encoded = $img->toGif(); break;
                                default:
                                    throw new RuntimeException('Unsupported output.');
                            }

                            $dataUri = $toDataUri($encoded->toString(), $mimeType);
                            $ok = "Image converted successfully to " . strtoupper($outFormat) . ".";
                        }
                    }

                } catch (Throwable $e) {
                    $err['process'] = "Processing failed: " . $e->getMessage();
                    $dataUri = null;
                    $downloadName = null;
                }
            }
        }
    }
}
?>

<div class="container-fluid">
    <?php include("include/temb/navbar.php"); ?>

    <form action="" method="post" enctype="multipart/form-data">
        <div class="container my-5">
            <div class="row g-4">
                <!-- Left Side - Upload & Formats -->
                <div class="col-lg-6">
                    <div class="card shadow-sm border-0">
                        <div class="card-body p-4">
                            <div class="upload-zone mb-4">
                                <input type="file" name="image" accept="image/*" required id="imageInput" class="form-control d-none">
                                <div class="upload-text text-center">
                                    <i class="bi bi-cloud-upload fs-1 text-muted mb-3"></i>
                                    <p class="mb-2">Drag & Drop or Click to Upload</p>
                                    <label for="imageInput" class="btn btn-primary">اختيار الملف</label>
                                </div>
                            </div>

                            <div class="format-section">
                                <h5 class="text-center mb-3 fw-bold">اختر صيغة التحويل</h5>
                                <div class="row g-2 mb-3">
                                    <div class="col-4"><button type="button" class="btn btn-outline-info w-100 format-btn active" onclick="selectFormat(this, 'webp')">WEBP</button></div>
                                    <div class="col-4"><button type="button" class="btn btn-outline-info w-100 format-btn" onclick="selectFormat(this, 'avif')">AVIF</button></div>
                                    <div class="col-4"><button type="button" class="btn btn-outline-info w-100 format-btn" onclick="selectFormat(this, 'jpeg')">JPEG</button></div>
                                    <div class="col-4"><button type="button" class="btn btn-outline-info w-100 format-btn" onclick="selectFormat(this, 'png')">PNG</button></div>
                                    <div class="col-4"><button type="button" class="btn btn-outline-info w-100 format-btn" onclick="selectFormat(this, 'gif')">GIF</button></div>
                                    <div class="col-4"><button type="button" class="btn btn-outline-info w-100 format-btn" onclick="selectFormat(this, 'svg')">SVG</button></div>
                                </div>
                                <input type="hidden" name="format" id="selectedFormat" value="webp">
                                <button type="submit" class="btn btn-success w-100 btn-lg">
                                    <i class="bi bi-arrow-repeat me-2"></i>Convert Image
                                </button>
                            </div>

                            <?php
                            if (!empty($err)) {
                                echo '<div class="alert alert-danger mt-3"><ul class="mb-0">';
                                foreach ($err as $e) {
                                    echo '<li>' . htmlspecialchars($e, ENT_QUOTES, 'UTF-8') . '</li>';
                                }
                                echo '</ul></div>';
                            } elseif (!empty($ok)) {
                                echo '<div class="alert alert-success mt-3">'.htmlspecialchars($ok, ENT_QUOTES, 'UTF-8').'</div>';
                            }
                            ?>
                        </div>
                    </div>
                </div>

                <!-- Right Side - Preview & Download -->
                <div class="col-lg-6">
                    <div class="card shadow-sm border-0">
                        <div class="card-body p-4">
                            <h5 class="card-title fw-bold mb-3">Preview/Download</h5>
                            <div class="preview-area bg-light rounded p-4 text-center">
                                <?php if ($dataUri): ?>
                                    <img src="<?php echo htmlspecialchars($dataUri, ENT_QUOTES, 'UTF-8'); ?>"
                                         alt="Converted Image" class="img-fluid rounded shadow">
                                <?php else: ?>
                                    <div class="preview-placeholder">
                                        <i class="bi bi-image fs-1 text-muted"></i>
                                        <p class="text-muted mt-2">No image yet</p>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <?php if ($dataUri && $downloadName): ?>
                                <div class="mt-3">
                                    <a href="<?php echo htmlspecialchars($dataUri, ENT_QUOTES, 'UTF-8'); ?>"
                                       download="<?php echo htmlspecialchars($downloadName, ENT_QUOTES, 'UTF-8'); ?>"
                                       class="btn btn-primary w-100 btn-lg">
                                        <i class="bi bi-download me-2"></i>Download Image
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </form>
</div>

<?php include("include/temb/footer.php"); ?>

<script>
/**
 * Select output format and update UI
 * @param {HTMLElement} element - The clicked button element
 * @param {string} format - The selected format (webp, avif, jpeg, png, gif, svg)
 */
function selectFormat(element, format) {
    // Update hidden input value
    document.getElementById('selectedFormat').value = format;
    
    // Remove active class from all buttons
    document.querySelectorAll('.format-btn').forEach(button => {
        button.classList.remove('active');
    });
    
    // Add active class to clicked button
    element.classList.add('active');
}
</script>
