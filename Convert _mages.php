<?php
require 'vendor/autoload.php';

use Intervention\Image\ImageManager;

include("include/DB/db.php");
include("include/temb/header.php");

/* -------------------- Config -------------------- */
$allowed_exts  = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'svg'];
$allowed_mimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/avif', 'image/svg+xml'];
$maxBytes      = 8 * 1024 * 1024; // 8MB
$uploadDir     = __DIR__ . '/uploads/';

// Clean old files (older than 30 minutes)
if (is_dir($uploadDir)) {
    $files = glob($uploadDir . '*');
    $now = time();
    foreach ($files as $file) {
        if (is_file($file) && ($now - filemtime($file)) > 1800) { // 30 minutes
            unlink($file);
        }
    }
}

$err = [];
$ok  = null;
$convertedImage = null;

/* -------------------- Handle POST -------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Ensure file exists
    if (!isset($_FILES['image']) || !is_array($_FILES['image'])) {
        $err['image'] = "Please upload an image.";
    } else {
        $image = $_FILES['image'];

        // Basic upload checks
        if ($image['error'] !== UPLOAD_ERR_OK) {
            $err['image'] = "Upload error code: " . $image['error'];
        } else {

            // Size check
            if ($image['size'] > $maxBytes) {
                $err['size'] = "Max file size is " . (int)round($maxBytes / 1024 / 1024) . "MB.";
            }

            // Extension (from name)
            $ext = strtolower(pathinfo($image['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, $allowed_exts, true)) {
                $err['ext'] = "Allowed extensions: " . implode(', ', $allowed_exts);
            }

            // MIME (real type from content)
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime  = finfo_file($finfo, $image['tmp_name']);
            finfo_close($finfo);

            if (!in_array($mime, $allowed_mimes, true)) {
                $err['mime'] = "Invalid MIME type: $mime";
            }

            // Raster sanity (skip for SVG which is text-based)
            if ($mime !== 'image/svg+xml' && getimagesize($image['tmp_name']) === false) {
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

                // Prepare uploads dir
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }

                // Sanitize base name + add random suffix
                $baseName = preg_replace('/[^A-Za-z0-9_\-]+/', '_', pathinfo($image['name'], PATHINFO_FILENAME)) ?: 'image';
                $uniq     = bin2hex(random_bytes(4));

                // Choose driver (Intervention v3)
                $driver  = extension_loaded('imagick') ? 'imagick' : 'gd';
                $manager = ($driver === 'imagick') ? ImageManager::imagick() : ImageManager::gd();

                /* ------------ SVG input ------------ */
                if ($mime === 'image/svg+xml') {
                    if ($outFormat === 'svg') {
                        // SVG -> SVG (no conversion)
                        $fileName = "{$baseName}_{$uniq}.svg";
                        $savePath = $uploadDir . $fileName;

                        // Use move_uploaded_file for uploaded files
                        if (!move_uploaded_file($image['tmp_name'], $savePath)) {
                            $err['move'] = "Failed to save the SVG file.";
                        } else {
                            $ok = "SVG uploaded successfully (no conversion).";
                            $convertedImage = 'uploads/' . $fileName;
                        }
                    } else {
                        // SVG -> raster (requires Imagick to rasterize)
                        if (!extension_loaded('imagick')) {
                            $err['imagick'] = "Imagick extension required to convert SVG to raster formats.";
                        } else {
                            try {
                                $img = $manager->read($image['tmp_name']); // may fail if SVG support is missing
                                $fileName = "{$baseName}_{$uniq}.{$outFormat}";
                                $savePath = $uploadDir . $fileName;

                                // Save with quality for supported formats
                                $quality = in_array($outFormat, ['jpeg', 'webp', 'avif'], true) ? 95 : null;
                                if ($quality !== null) {
                                    $img->save($savePath, $quality);
                                } else {
                                    $img->save($savePath);
                                }

                                $ok = "SVG converted successfully to " . strtoupper($outFormat) . ".";
                                $convertedImage = 'uploads/' . $fileName;
                            } catch (Exception $e) {
                                $err['process'] = "SVG conversion failed: " . $e->getMessage();
                            }
                        }
                    }

                    /* -------- Raster input (JPEG/PNG/GIF/WebP/AVIF...) -------- */
                } else {

                    // Raster -> SVG (vectorization) via potrace (optional feature)
                    if ($outFormat === 'svg') {
                        $fileName = "{$baseName}_{$uniq}.svg";
                        $savePath = $uploadDir . $fileName;

                        // Check potrace availability
                        $whichPotrace = shell_exec('command -v potrace 2>&1');
                        if (empty($whichPotrace)) {
                            $err['potrace'] = "Potrace is not installed. Install it to convert raster images to SVG.";
                        } else {
                            // Convert to temporary BMP (potrace expects BMP)
                            try {
                                $img = $manager->read($image['tmp_name']);

                                $tmpBmp = $uploadDir . "tmp_{$uniq}.bmp";
                                $img->save($tmpBmp);

                                // Run potrace safely
                                $cmd = "potrace " . escapeshellarg($tmpBmp) . " -s -o " . escapeshellarg($savePath) . " 2>&1";
                                $output = shell_exec($cmd);

                                // Cleanup temp
                                if (file_exists($tmpBmp)) {
                                    unlink($tmpBmp);
                                }

                                // Validate result
                                if (file_exists($savePath) && filesize($savePath) > 0) {
                                    $ok = "Image converted successfully to SVG (vectorized).";
                                    $convertedImage = 'uploads/' . $fileName;
                                } else {
                                    $err['conversion'] = "SVG conversion failed. Output: " . (string)$output;
                                }
                            } catch (Exception $e) {
                                $err['process'] = "Process failed: " . $e->getMessage();
                            }
                        }
                    } else {
                        // Raster -> Raster conversion
                        try {
                            $img = $manager->read($image['tmp_name']);

                            // Optional: downscale large images
                            // if ($img->width() > 1600) {
                            //     $img->resize(1600, null, function($c){ $c->aspectRatio(); $c->upsize(); });
                            // }

                            $fileName = "{$baseName}_{$uniq}.{$outFormat}";
                            $savePath = $uploadDir . $fileName;

                            // Save with quality for supported formats
                            $quality = in_array($outFormat, ['jpeg', 'webp', 'avif'], true) ? 95 : null;
                            if ($quality !== null) {
                                $img->save($savePath, $quality);
                            } else {
                                $img->save($savePath);
                            }

                            $ok = "Image converted successfully to " . strtoupper($outFormat) . ".";
                            $convertedImage = 'uploads/' . $fileName;
                        } catch (Exception $e) {
                            $err['save'] = "Failed to save converted image: " . $e->getMessage();
                        }
                    }
                }
            }
        }
    }
}
?>



<div class="container-fluid">
    <!-- Header -->
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
                                    <div class="col-4">
                                        <button type="button" class="btn btn-outline-info w-100 format-btn active" onclick="selectFormat('webp')">WEBP</button>
                                    </div>
                                    <div class="col-4">
                                        <button type="button" class="btn btn-outline-info w-100 format-btn" onclick="selectFormat('avif')">AVIF</button>
                                    </div>
                                    <div class="col-4">
                                        <button type="button" class="btn btn-outline-info w-100 format-btn" onclick="selectFormat('jpeg')">JPEG</button>
                                    </div>
                                    <div class="col-4">
                                        <button type="button" class="btn btn-outline-info w-100 format-btn" onclick="selectFormat('png')">PNG</button>
                                    </div>
                                    <div class="col-4">
                                        <button type="button" class="btn btn-outline-info w-100 format-btn" onclick="selectFormat('gif')">GIF</button>
                                    </div>
                                    <div class="col-4">
                                        <button type="button" class="btn btn-outline-info w-100 format-btn" onclick="selectFormat('svg')">SVG</button>
                                    </div>
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
                                <?php if (!empty($ok) && $convertedImage): ?>
                                    <img src="<?php echo htmlspecialchars($convertedImage, ENT_QUOTES, 'UTF-8'); ?>" 
                                         alt="Converted Image" class="img-fluid rounded shadow">
                                <?php else: ?>
                                    <div class="preview-placeholder">
                                        <i class="bi bi-image fs-1 text-muted"></i>
                                        <p class="text-muted mt-2">No image yet</p>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <?php if (!empty($ok) && $convertedImage): ?>
                                <div class="mt-3">
                                    <?php
                                    $filename = basename($convertedImage);
                                    echo '<a href="download.php?file=' . urlencode($filename) . '" class="btn btn-primary w-100 btn-lg">
                                            <i class="bi bi-download me-2"></i>Download Image
                                          </a>';
                                    ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>

    <!-- Footer -->
</div>

<?php include("include/temb/footer.php"); ?>