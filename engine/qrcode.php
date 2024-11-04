<?php
function createSimpleQrCodeArray($url) {
    // Convert URL to binary representation (basic encoding)
    $binaryUrl = '';
    foreach (str_split($url) as $char) {
        $binaryUrl .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
    }

    $size = 21; // standard????
    $booleanArray = array_fill(0, $size, array_fill(0, $size, false));

    // Fill the matrix with black (true) and white (false) based on binary representation
    $row = 0;
    $col = 0;

    // Fill the matrix with binary representation
    for ($i = 0; $i < strlen($binaryUrl); $i++) {
        if ($i >= $size * $size) break; // Prevent overflow
        $booleanArray[$row][$col] = $binaryUrl[$i] == '1'; // true for 1, false for 0
        $col++;
        if ($col >= $size) {
            $col = 0;
            $row++;
        }
    }

    // Add finder patterns
    addFinderPattern($booleanArray, 0, 0); // Top-left
    addFinderPattern($booleanArray, 0, $size - 7); // Top-right
    addFinderPattern($booleanArray, $size - 7, 0); // Bottom-left

    // Add timing patterns
    for ($i = 8; $i < $size - 8; $i++) {
        $booleanArray[6][$i] = $i % 2 == 0; // Horizontal timing pattern
        $booleanArray[$i][6] = $i % 2 == 0; // Vertical timing pattern
    }

    return $booleanArray;
}

// Function to add a finder pattern at the specified position
function addFinderPattern(&$matrix, $startRow, $startCol) {
    for ($i = 0; $i < 7; $i++) {
        for ($j = 0; $j < 7; $j++) {
            if ($i == 0 || $i == 6 || $j == 0 || $j == 6 || ($i >= 2 && $i <= 4 && $j >= 2 && $j <= 4)) {
                $matrix[$startRow + $i][$startCol + $j] = true; // Black
            } else {
                $matrix[$startRow + $i][$startCol + $j] = false; // White
            }
        }
    }
}


$url = "https://example.com";
$qrCodeArray = createSimpleQrCodeArray($url)

// please help me, even I don't know what tf am I doing. T^T

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QR Code</title>
    <style>
        body {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            background-color: #f0f0f0;
        }
        .qr-code {
            display: grid;
            grid-template-columns: repeat(21, 20px); /* 21 columns */
            gap: 0;
        }
        .qr-code div {
            width: 20px;
            height: 20px;
        }
        .qr-code .black {
            background-color: black;
        }
        .qr-code .white {
            background-color: white;
        }
    </style>
</head>
<body>
<div class="qr-code">
    <?php foreach ($qrCodeArray as $row): ?>
        <?php foreach ($row as $cell): ?>
            <div class="<?= $cell ? 'black' : 'white' ?>"></div>
        <?php endforeach; ?>
    <?php endforeach; ?>
</div>
</body>
</html>


