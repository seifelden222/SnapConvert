<?php
require 'vendor/autoload.php';

use Intervention\Image\ImageManager;

include("include/DB/db.php");
include("include/temb/header.php");

/* -------------------- Config -------------------- */
$allowed_exts  = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif'];
$allowed_mimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/avif'];
$maxBytes      = 10 * 1024 * 1024; // 10MB

$err = [];
$ok  = null;
$compressedDataUri = null;
$downloadName = null;
$originalSize = 0;
$compressedSize = 0;
$reduction = 0;

/* -------------------- Handle POST -------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!isset($_FILES['image']) || !is_array($_FILES['image'])) {
        $err['image'] = "Please upload an image.";
    } else {
        $image = $_FILES['image'];

        // Check upload errors
        if ($image['error'] !== UPLOAD_ERR_OK) {
            $err['image'] = "Upload error code: " . $image['error'];
        } else {

            // Check file size
            if ($image['size'] > $maxBytes) {
                $err['size'] = "Max file size is " . (int)round($maxBytes / 1024 / 1024) . "MB.";
            }

            // Check extension
            $ext = strtolower(pathinfo($image['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, $allowed_exts, true)) {
                $err['ext'] = "Allowed extensions: " . implode(', ', $allowed_exts);
            }

            // Check MIME type
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime  = finfo_file($finfo, $image['tmp_name']);
            finfo_close($finfo);

            if (!in_array($mime, $allowed_mimes, true)) {
                $err['mime'] = "Invalid MIME type: $mime";
            }

            // Check if it's a real image (only for basic raster formats)
            $rasterCheckMimes = ['image/jpeg', 'image/png', 'image/gif'];
            if (in_array($mime, $rasterCheckMimes, true) && @getimagesize($image['tmp_name']) === false) {
                $err['real'] = "The uploaded file is not a real image.";
            }

            // Get quality (1-100) - default 75 for compression
            $quality = isset($_POST['quality']) ? (int)$_POST['quality'] : 75;
            $quality = max(1, min(100, $quality)); // Clamp between 1-100

            if (empty($err)) {
                $ok = "Checks passed. Ready to compress.";

                $baseName = preg_replace('/[^A-Za-z0-9_\-]+/', '_', pathinfo($image['name'], PATHINFO_FILENAME)) ?: 'image';
                $downloadName = $baseName . '_compressed.' . $ext;

                // Choose Intervention v3 driver (imagick if available, otherwise gd)
                $driver  = extension_loaded('imagick') ? 'imagick' : 'gd';
                $manager = ($driver === 'imagick') ? ImageManager::imagick() : ImageManager::gd();

                // Helper to convert bytes to data URI
                $toDataUri = function (string $bytes, string $mimeType) {
                    return 'data:' . $mimeType . ';base64,' . base64_encode($bytes);
                };

                try {
                    // Read original image
                    $img = $manager->read($image['tmp_name']);
                    
                    // Store original size
                    $originalSize = $image['size'];

                    // Compress based on format
                    $mimeType = $mime;
                    switch ($ext) {
                        case 'jpg':
                        case 'jpeg':
                            $encoded = $img->toJpeg($quality);
                            $mimeType = 'image/jpeg';
                            break;
                        case 'png':
                            // PNG: Use compression
                            $encoded = $img->toPng();
                            $mimeType = 'image/png';
                            break;
                        case 'webp':
                            $encoded = method_exists($img, 'toWebp') 
                                ? $img->toWebp($quality) 
                                : $img->encodeByExtension('webp', $quality);
                            $mimeType = 'image/webp';
                            break;
                        case 'avif':
                            $encoded = method_exists($img, 'toAvif') 
                                ? $img->toAvif($quality) 
                                : $img->encodeByExtension('avif', $quality);
                            $mimeType = 'image/avif';
                            break;
                        case 'gif':
                            $encoded = $img->toGif();
                            $mimeType = 'image/gif';
                            break;
                        default:
                            throw new RuntimeException('Unsupported format.');
                    }

                    // Get compressed size
                    $compressedBytes = $encoded->toString();
                    $compressedSize = strlen($compressedBytes);
                    
                    // Calculate reduction
                    $reduction = round((1 - $compressedSize / $originalSize) * 100, 1);
                    
                    // Create compressed data URI
                    $compressedDataUri = $toDataUri($compressedBytes, $mimeType);
                    
                    // Format sizes for display
                    $originalSizeKB = round($originalSize / 1024, 1);
                    $compressedSizeKB = round($compressedSize / 1024, 1);
                    
                    if ($reduction > 0) {
                        $ok = "Image compressed successfully! "
                            . "Original: {$originalSizeKB}KB → Compressed: {$compressedSizeKB}KB "
                            . "(Reduced by {$reduction}% at Quality: {$quality}%)";
                    } else {
                        $ok = "Image processed. Size: {$compressedSizeKB}KB (Quality: {$quality}%)";
                    }

                } catch (Throwable $e) {
                    $err['process'] = "Compression failed: " . $e->getMessage();
                    $compressedDataUri = null;
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
                <!-- Left Side - Upload & Settings -->
                <div class="col-lg-6">
                    <div class="card shadow-sm border-0">
                        <div class="card-body p-4">
                            <h4 class="card-title fw-bold mb-4 text-center">
                                <i class="bi bi-file-zip me-2"></i>Compress Image
                            </h4>
                            
                            <div class="upload-zone mb-4">
                                <input type="file" name="image" accept="image/*" required id="imageInput" class="form-control d-none">
                                <div class="upload-text text-center">
                                    <i class="bi bi-cloud-upload fs-1 text-muted mb-3"></i>
                                    <p class="mb-2">Drag & Drop or Click to Upload</p>
                                    <label for="imageInput" class="btn btn-primary">Choose Image</label>
                                    <p class="text-muted small mt-2">Max size: 10MB • Formats: JPG, PNG, WEBP, AVIF, GIF</p>
                                </div>
                            </div>

                            <!-- Quality Slider -->
                            <div class="quality-section mb-4">
                                <label for="qualitySlider" class="form-label fw-bold">
                                    Compression Quality: <span id="qualityValue" class="text-primary">75</span>%
                                </label>
                                <input type="range" class="form-range" min="1" max="100" value="75" 
                                       id="qualitySlider" name="quality" oninput="updateQualityLabel(this.value)">
                                <div class="d-flex justify-content-between text-muted small">
                                    <span><i class="bi bi-arrow-down-circle"></i> Smaller File</span>
                                    <span><i class="bi bi-arrow-up-circle"></i> Better Quality</span>
                                </div>
                                <div class="alert alert-info small mt-2 mb-0">
                                    <i class="bi bi-info-circle me-1"></i>
                                    <strong>Tip:</strong> 70-85% gives best balance between quality and size.
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-success w-100 btn-lg">
                                <i class="bi bi-file-zip me-2"></i>Compress Image
                            </button>

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
                            <h5 class="card-title fw-bold mb-3">Result</h5>
                            <div class="preview-area bg-light rounded p-4 text-center">
                                <?php if ($compressedDataUri): ?>
                                    <img src="<?php echo htmlspecialchars($compressedDataUri, ENT_QUOTES, 'UTF-8'); ?>"
                                         alt="Compressed Image" class="img-fluid rounded shadow">
                                    
                                    <!-- Stats -->
                                    <div class="stats mt-3">
                                        <div class="row text-center">
                                            <div class="col-4">
                                                <div class="stat-box p-2 border rounded">
                                                    <small class="text-muted">Original</small>
                                                    <div class="fw-bold"><?php echo round($originalSize / 1024, 1); ?>KB</div>
                                                </div>
                                            </div>
                                            <div class="col-4">
                                                <div class="stat-box p-2 border rounded bg-success text-white">
                                                    <small>Compressed</small>
                                                    <div class="fw-bold"><?php echo round($compressedSize / 1024, 1); ?>KB</div>
                                                </div>
                                            </div>
                                            <div class="col-4">
                                                <div class="stat-box p-2 border rounded">
                                                    <small class="text-muted">Saved</small>
                                                    <div class="fw-bold text-success"><?php echo $reduction; ?>%</div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div class="preview-placeholder">
                                        <i class="bi bi-image fs-1 text-muted"></i>
                                        <p class="text-muted mt-2">No image compressed yet</p>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <?php if ($compressedDataUri && $downloadName): ?>
                                <div class="mt-3">
                                    <a href="<?php echo htmlspecialchars($compressedDataUri, ENT_QUOTES, 'UTF-8'); ?>"
                                       download="<?php echo htmlspecialchars($downloadName, ENT_QUOTES, 'UTF-8'); ?>"
                                       class="btn btn-primary w-100 btn-lg">
                                        <i class="bi bi-download me-2"></i>Download Compressed Image
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
 * Update quality label when slider changes
 * @param {number} value - Quality value (1-100)
 */
function updateQualityLabel(value) {
    document.getElementById('qualityValue').textContent = value;
}
</script>