<?php
require 'vendor/autoload.php';

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Color\Color;

include("include/DB/db.php");
include("include/temb/header.php");

$qrCodePath = null;
$qrCodeDataUri = null;
$error = null;
$success = null;

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['generate'])) {

    $text = $_POST["text"] ?? '';
    $bgColorHex = $_POST['backgroundColor'] ?? '#ffffff';
    $fgColorHex = $_POST['foregroundColor'] ?? '#000';

    if (empty($text)) {
        $error = "Please enter text to generate QR Code.";
    } else {
        try {
            // Convert hex colors to RGB
            $bgHex = ltrim($bgColorHex, '#');
            $bgR = hexdec(substr($bgHex, 0, 2));
            $bgG = hexdec(substr($bgHex, 2, 2));
            $bgB = hexdec(substr($bgHex, 4, 2));
            
            $fgHex = ltrim($fgColorHex, '#');
            $fgR = hexdec(substr($fgHex, 0, 2));
            $fgG = hexdec(substr($fgHex, 2, 2));
            $fgB = hexdec(substr($fgHex, 4, 2));

            // Generate QR Code in memory (no file saved)
            $builder = new Builder(
                writer: new PngWriter(),
                data: $text,
                backgroundColor: new Color($bgR, $bgG, $bgB),
                foregroundColor: new Color($fgR, $fgG, $fgB),
                encoding: new Encoding('UTF-8'),
                errorCorrectionLevel: ErrorCorrectionLevel::High,
                size: 300,
                margin: 10,
                roundBlockSizeMode: RoundBlockSizeMode::Margin
            );

            $result = $builder->build();

            // Convert to base64 data URI (no file saved on server)
            $qrCodeDataUri = $result->getDataUri();
            $success = "QR Code generated successfully!";
        } catch (Exception $e) {
            $error = "Error generating QR Code: " . $e->getMessage();
        }
    }
}
?>
<div class="container-fluid">
    <?php include("include/temb/navbar.php"); ?>

    <div class="container my-5">
        <div class="row justify-content-center">
            <div class="col-lg-6">
                <div class="card shadow-sm border-0">
                    <div class="card-body p-4">
                        <h3 class="text-center mb-4 fw-bold">QR Code Generator</h3>

                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                        <?php endif; ?>

                        <?php if ($success): ?>
                            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                        <?php endif; ?>

                        <form action="" method="post">
                            <div class="mb-3">
                                <label for="text" class="form-label fw-bold">Enter Text or URL:</label>
                                <input type="text" id="text" name="text" class="form-control form-control-lg" required placeholder="https://example.com or any text">
                            </div>
                            
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="backgroundColor" class="form-label fw-bold">Background Color:</label>
                                    <div class="d-flex align-items-center">
                                        <input type="color" id="backgroundColor" name="backgroundColor" class="form-control form-control-color me-2" value="#ffffff" style="width: 60px; height: 45px;">
                                        <span class="text-muted" id="bgColorValue">#ffffff</span>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label for="foregroundColor" class="form-label fw-bold">QR Code Color:</label>
                                    <div class="d-flex align-items-center">
                                        <input type="color" id="foregroundColor" name="foregroundColor" class="form-control form-control-color me-2" value="#000000" style="width: 60px; height: 45px;">
                                        <span class="text-muted" id="fgColorValue">#000000</span>
                                    </div>
                                </div>
                            </div>
                            
                            <button type="submit" name="generate" class="btn btn-primary w-100 btn-lg">
                                <i class="bi bi-qr-code me-2"></i>Generate QR Code
                            </button>
                        </form>

                        <?php if ($qrCodeDataUri): ?>
                            <div class="mt-4 text-center">
                                <h5 class="mb-3">Your QR Code:</h5>
                                <div class="bg-light p-4 rounded">
                                    <img src="<?php echo $qrCodeDataUri; ?>" alt="QR Code" class="img-fluid rounded shadow" id="qrCodeImage">
                                </div>
                                <button type="button" class="btn btn-success mt-3 w-100" onclick="downloadQRCode()">
                                    <i class="bi bi-download me-2"></i>Download QR Code
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Update color value display when color changes
    document.getElementById('backgroundColor').addEventListener('input', function(e) {
        document.getElementById('bgColorValue').textContent = e.target.value.toUpperCase();
    });
    
    document.getElementById('foregroundColor').addEventListener('input', function(e) {
        document.getElementById('fgColorValue').textContent = e.target.value.toUpperCase();
    });
    
    // Download QR code directly from data URI (no server storage)
    function downloadQRCode() {
        const img = document.getElementById('qrCodeImage');
        const link = document.createElement('a');
        link.href = img.src;
        link.download = 'qr-code-' + Date.now() + '.png';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }
</script>

<?php include("include/temb/footer.php"); ?>