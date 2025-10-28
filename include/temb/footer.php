<footer class="custom-footer mt-5">
    <div class="container py-4">
        <div class="row">
            <div class="col-md-6 mb-3 mb-md-0">
                <h6 class="footer-title mb-3">Developer Info</h6>
                <p class="footer-text mb-2"><strong>Name:</strong> SeifElden Hamdy</p>
                <p class="footer-text mb-2">
                    <strong>Email:</strong>
                    <a href="mailto:seifeldenhamdy@gmail.com" class="footer-link">seifeldenhamdy@gmail.com</a>
                </p>
                <p class="footer-text mb-0">
                    <strong>Phone:</strong>
                    <a href="tel:0103248494" class="footer-link">0103248494</a>
                </p>
            </div>
            <div class="col-md-6 text-md-end">
                <h6 class="footer-title mb-3">SnapConvert</h6>
                <p class="footer-text mb-2">© 2024-2025 All Rights Reserved</p>
                <p class="footer-text-muted mb-0">Professional Image Converter Tool</p>
            </div>
        </div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-MrcW6ZMFYlzcLA8Nl+NtUVF0sA7MsXsP1UyJoMp4YLEuNSfAP+JcXn/tWtIaxVXM" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.2/dist/umd/popper.min.js" integrity="sha384-IQsoLXl5PILFhosVNubq5LC7Qb9DXgDA9i+tQ8Zj3iwWAwPtgFTxbJ8NT4GN1R8p" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.min.js" integrity="sha384-cVKIPhGWiC2Al4u+LWgxfKTRIcfu0JTxR+EQDz/bgldoEyl4H0zUF0QKbrJ0EcQF" crossorigin="anonymous"></script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
<script>
    function selectFormat(format) {
        // Remove active class from all buttons
        document.querySelectorAll('.format-btn').forEach(btn => {
            btn.classList.remove('active');
        });

        // Add active class to clicked button
        event.target.classList.add('active');

        // Set hidden input value
        document.getElementById('selectedFormat').value = format;
    }

    // Show file name when selected
    document.getElementById('imageInput').addEventListener('change', function(e) {
        if (e.target.files.length > 0) {
            const fileName = e.target.files[0].name;
            document.querySelector('.upload-text p').innerHTML = '<strong class="text-primary">' + fileName + '</strong><br>File selected!';
        }
    });
</script>

</body>

</html>