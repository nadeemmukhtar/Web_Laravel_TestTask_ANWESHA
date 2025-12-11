<div class="p-6 bg-white shadow rounded-lg">
    <form action="{{ url('/uploads/image') }}" class="dropzone" id="my-dropzone" enctype="multipart/form-data">
        @csrf

        <input type="hidden" name="checksum" id="checksum" value="">
        <input type="hidden" name="product_sku" value="{{ $productSku ?? '' }}">
        <div class="dz-message text-gray-500 text-center p-10">
            Drag & drop your primary image here, or click to select.<br>
            (Chunked/resumable upload supported)
        </div>
    </form>

    <!-- Preview / Progress -->
    <div id="upload-progress" class="mt-4 hidden">
        <div class="h-2 bg-gray-200 rounded-full">
            <div id="upload-bar" class="h-2 bg-blue-600 rounded-full transition-all" style="width:0%"></div>
        </div>
        <p id="upload-percent" class="text-sm mt-1 text-gray-600">Uploading: 0%</p>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/crypto-js/4.2.0/crypto-js.min.js"></script>

<script>
    Dropzone.autoDiscover = false;

    const myDropzone = new Dropzone("#my-dropzone", {
        paramName: "file",
        maxFilesize: 200, // MB
        acceptedFiles: "image/*",
        addRemoveLinks: true,
        chunking: true,
        forceChunking: true,
        chunkSize: 2 * 1024 * 1024, // 2MB per chunk
        retryChunks: true,
        retryChunksLimit: 3,

        init: function() {
            this.on("addedfile", function(file) {
                const reader = new FileReader();
                reader.readAsArrayBuffer(file);

                reader.onload = function(e) {
                    const buffer = e.target.result;
                    const wordArray = CryptoJS.lib.WordArray.create(buffer);
                    const checksum = CryptoJS.MD5(wordArray).toString();
                    document.getElementById("checksum").value = checksum;
                };
            });

            this.on("uploadprogress", function(file, progress) {
                document.getElementById("upload-progress").classList.remove("hidden");
                document.getElementById("upload-bar").style.width = progress + "%";
                document.getElementById("upload-percent").innerText = "Uploading: " + Math.round(
                    progress) + "%";
            });

            this.on("sending", function(file, xhr, formData) {
                // Add checksum and product SKU to each chunk
                formData.append("checksum", document.getElementById("checksum").value);
                formData.append("product_sku", "{{ $productSku ?? '' }}");
            });

            this.on("success", function(file, response) {
                alert("Image uploaded successfully!");
            });

            this.on("error", function(file, response) {
                alert("Upload failed!");
            });
        }
    });
</script>
