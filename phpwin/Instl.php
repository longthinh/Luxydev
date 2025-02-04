<?php
$uploadDir = __DIR__ . '/uploads/';
if (is_dir($uploadDir)) {
  $files = glob($uploadDir . '*');
  foreach ($files as $file) {
    if (is_file($file)) {
      unlink($file);
    }
  }
} else {
  mkdir($uploadDir, 0755, true);
}

$protocol = 'http';
$host = $_SERVER['HTTP_HOST'];
$path = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
$baseUrl = $protocol . "://" . $host . $path . '/uploads/';

function parsePlistDict($dict)
{
  $result = [];
  $children = $dict->children();
  $key = null;
  foreach ($children as $child) {
    if ($child->getName() === 'key') {
      $key = (string)$child;
    } else {
      if ($key !== null) {
        $result[$key] = (string)$child;
        $key = null;
      }
    }
  }
  return $result;
}

function uploadPlistToApi($plistPath)
{
  $apiUrl = 'https://tmpfiles.dabeecao.org/upload';
  if (!file_exists($plistPath)) {
    return false;
  }
  $cfile = new CURLFile($plistPath, 'application/xml', basename($plistPath));
  $postfields = ['file' => $cfile];
  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, $apiUrl);
  curl_setopt($ch, CURLOPT_POST, true);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_POSTFIELDS, $postfields);
  $response = curl_exec($ch);
  if (curl_errno($ch)) {
    curl_close($ch);
    return false;
  }
  curl_close($ch);
  $json = json_decode($response, true);
  if (isset($json['url'])) {
    return $json['url'];
  }
  return false;
}

$message = '';
$installLink = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (isset($_FILES['ipa']) && $_FILES['ipa']['error'] === UPLOAD_ERR_OK) {
    $ipaTmpPath = $_FILES['ipa']['tmp_name'];
    $ipaName = basename($_FILES['ipa']['name']);
    if (strtolower(pathinfo($ipaName, PATHINFO_EXTENSION)) !== 'ipa') {
      $message = "Please upload a file in .ipa format";
    } else {
      $ipaName = preg_replace('/\s+/', '_', $ipaName);
      $newIpaName = time() . '_' . $ipaName;
      $ipaDestPath = $uploadDir . $newIpaName;
      if (move_uploaded_file($ipaTmpPath, $ipaDestPath)) {
        $zip = new ZipArchive;
        if ($zip->open($ipaDestPath) === true) {
          $plistContent = false;
          for ($i = 0; $i < $zip->numFiles; $i++) {
            $entry = $zip->getNameIndex($i);
            if (preg_match('#^Payload/[^/]+\.app/Info\.plist$#', $entry)) {
              $plistContent = $zip->getFromName($entry);
              break;
            }
          }
          $zip->close();
          if ($plistContent === false) {
            $message = "Could not find Info.plist in the ipa file";
          } else {
            $xml = simplexml_load_string($plistContent, 'SimpleXMLElement', LIBXML_NOCDATA);
            if ($xml === false || !isset($xml->dict)) {
              $message = "Unable to parse the Info.plist file";
            } else {
              $plistData = parsePlistDict($xml->dict);
              $bundleIdentifier = isset($plistData['CFBundleIdentifier']) ? $plistData['CFBundleIdentifier'] : 'unknown.bundle.id';
              $appName = isset($plistData['CFBundleName']) ? $plistData['CFBundleName'] : 'Application';

              $manifest = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
              $manifest .= '<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">' . "\n";
              $manifest .= '<plist version="1.0">' . "\n";
              $manifest .= '<dict>' . "\n";
              $manifest .= '  <key>items</key>' . "\n";
              $manifest .= '  <array>' . "\n";
              $manifest .= '    <dict>' . "\n";
              $manifest .= '      <key>assets</key>' . "\n";
              $manifest .= '      <array>' . "\n";
              $manifest .= '        <dict>' . "\n";
              $manifest .= '          <key>kind</key>' . "\n";
              $manifest .= '          <string>software-package</string>' . "\n";
              $manifest .= '          <key>url</key>' . "\n";
              $ipaDownloadLink = $baseUrl . rawurlencode($newIpaName);
              $manifest .= '          <string>' . $ipaDownloadLink . '</string>' . "\n";
              $manifest .= '        </dict>' . "\n";
              $manifest .= '      </array>' . "\n";
              $manifest .= '      <key>metadata</key>' . "\n";
              $manifest .= '      <dict>' . "\n";
              $manifest .= '        <key>bundle-identifier</key>' . "\n";
              $manifest .= '        <string>' . htmlspecialchars($bundleIdentifier) . '</string>' . "\n";
              $manifest .= '        <key>kind</key>' . "\n";
              $manifest .= '        <string>software</string>' . "\n";
              $manifest .= '        <key>title</key>' . "\n";
              $manifest .= '        <string>' . htmlspecialchars($appName) . '</string>' . "\n";
              $manifest .= '      </dict>' . "\n";
              $manifest .= '    </dict>' . "\n";
              $manifest .= '  </array>' . "\n";
              $manifest .= '</dict>' . "\n";
              $manifest .= '</plist>';

              $manifestFileName = time() . '_manifest.plist';
              $manifestPath = $uploadDir . $manifestFileName;
              if (file_put_contents($manifestPath, $manifest)) {
                $uploadedPlistUrl = uploadPlistToApi($manifestPath);
                if ($uploadedPlistUrl !== false) {
                  $installLink = "itms-services://?action=download-manifest&url=" . urlencode($uploadedPlistUrl);
                  $message = "<strong>Success!</strong> Click <strong>Install</strong> wait 5 seconds before exiting to the Home Screen. <br>Name: " . htmlspecialchars($appName) . "<br>Bundle ID: " . htmlspecialchars($bundleIdentifier);
                } else {
                  $message = "Cannot upload the manifest file to the server";
                }
              } else {
                $message = "Cannot save the manifest file";
              }
            }
          }
        } else {
          $message = "Cannot open the ipa file";
        }
      } else {
        $message = "Cannot transfer the uploaded file";
      }
    }
  } else {
    $message = "Please select an ipa file to upload";
  }
}
?>
<!DOCTYPE html>
<html lang="vi">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <title>IPA INSTALLER</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    body {
      background: radial-gradient(ellipse at center, #000 0%, #050505 100%);
      color: #0f0;
      font-family: 'Courier New', Courier, monospace;
      text-shadow: 0 0 5px #0f0;
      margin: 0;
      padding: 0;
      height: 100vh;
      overflow: hidden;
    }

    .card {
      background: rgba(0, 0, 0, 0.85);
      box-shadow: 0 0 20px 5px rgba(0, 255, 0, 0.3);
      border-radius: 10px;
      padding: 20px;
      backdrop-filter: blur(5px);
    }

    .btn {
      background-color: transparent;
      color: #0f0;
      border: 1px solid #0f0;
      padding: 12px 0;
      transition: all 0.3s ease;
    }

    .btn:hover {
      background-color: #0f0;
      color: #000;
      box-shadow: 0 0 10px #0f0;
    }

    .input {
      background-color: transparent;
      color: #0f0;
      border: 1px solid #0f0;
      padding: 10px;
    }

    a {
      text-decoration: none;
    }
  </style>
</head>

<body class="flex items-center justify-center">
  <div class="card w-full max-w-md">
    <div class="text-center pb-4">
      <h2 class="text-3xl font-bold">IPA INSTALLER</h2>
    </div>
    <div class="p-4">
      <?php if (empty($installLink)): ?>
        <form method="post" enctype="multipart/form-data" class="space-y-4">
          <div>
            <label for="ipa" class="block">Choose ipa file:</label>
            <input type="file" id="ipa" name="ipa" accept=".ipa" required class="mt-2 p-2 w-full input rounded" />
          </div>
          <button type="submit" class="w-full py-3 btn rounded shadow transition">PROCESS</button>
        </form>
      <?php endif; ?>

      <?php if (!empty($message)): ?>
        <div class="mt-4 p-4 border border-green-500 rounded">
          <?php echo $message; ?>
        </div>
      <?php endif; ?>

      <?php if (!empty($installLink)): ?>
        <div class="mt-4 text-center">
          <a href="<?php echo $installLink; ?>" class="btn px-6 py-3 rounded transition inline-block">INSTALL</a>
        </div>
      <?php endif; ?>
    </div>
  </div>
</body>

</html>