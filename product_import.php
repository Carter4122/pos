<?php

function normalizeProductImportHeader($header)
{
    $header = strtolower(trim((string) $header));
    $header = preg_replace('/^\\xEF\\xBB\\xBF/', "", $header);
    $header = preg_replace('/[^a-z0-9]+/', "_", $header);
    $header = trim($header, "_");

    $aliases = [
        "product" => "product_name",
        "name" => "product_name",
        "item" => "product_name",
        "item_name" => "product_name",
        "code" => "product_code",
        "sku" => "product_code",
        "barcode" => "product_code",
        "product_id" => "product_code",
        "item_code" => "product_code",
        "product_sku" => "product_code",
        "product_category" => "category",
        "details" => "description",
        "cost" => "cost_price",
        "buying_price" => "cost_price",
        "purchase_price" => "cost_price",
        "selling" => "selling_price",
        "price" => "selling_price",
        "unit_price" => "selling_price",
        "retail_price" => "selling_price",
        "sales_price" => "selling_price",
        "sell_price" => "selling_price",
        "quantity" => "stock_quantity",
        "qty" => "stock_quantity",
        "stock" => "stock_quantity",
        "current_stock" => "stock_quantity",
        "opening_stock" => "stock_quantity",
        "reorder" => "reorder_level",
        "minimum_stock" => "reorder_level"
    ];

    $header = preg_replace('/_(required|optional)$/', "", $header);

    if (isset($aliases[$header])) {
        return $aliases[$header];
    }

    foreach (["cost_price", "selling_price", "stock_quantity", "reorder_level"] as $prefix) {
        if (strpos($header, $prefix . "_") === 0) {
            return $prefix;
        }
    }

    return $header;
}

function findProductImportHeader($rows)
{
    $requiredHeaders = ["product_name", "selling_price"];
    foreach (array_slice($rows, 0, 10, true) as $rowIndex => $row) {
        $headers = array_map("normalizeProductImportHeader", $row);
        $positions = [];
        foreach ($headers as $columnIndex => $header) {
            if ($header !== "" && !isset($positions[$header])) {
                $positions[$header] = $columnIndex;
            }
        }

        if (!array_diff($requiredHeaders, array_keys($positions))) {
            return ["row_index" => $rowIndex, "positions" => $positions];
        }
    }

    return null;
}

function readProductImportRows($filePath, $extension)
{
    if ($extension === "csv") {
        $handle = fopen($filePath, "rb");
        if (!$handle) {
            throw new RuntimeException("The uploaded CSV file could not be read.");
        }

        $sampleLines = [];
        while (count($sampleLines) < 20 && ($line = fgets($handle)) !== false) {
            $sampleLines[] = $line;
        }
        if (!$sampleLines) {
            fclose($handle);
            return [];
        }

        $delimiter = ",";
        $bestColumnCount = 1;
        foreach ([",", ";", "\t"] as $candidate) {
            foreach ($sampleLines as $line) {
                $columnCount = count(str_getcsv($line, $candidate));
                if ($columnCount > $bestColumnCount) {
                    $delimiter = $candidate;
                    $bestColumnCount = $columnCount;
                }
            }
        }

        rewind($handle);
        $rows = [];
        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            $rows[] = $row;
        }
        fclose($handle);
        return $rows;
    }

    if ($extension !== "xlsx") {
        throw new RuntimeException("Use a .csv or .xlsx file.");
    }

    if (!class_exists("ZipArchive")) {
        throw new RuntimeException(
            "XLSX upload is unavailable because PHP's ZIP extension is disabled. Enable extension=zip in C:\\xampp\\php\\php.ini and restart Apache, or save the workbook as CSV."
        );
    }

    $archive = new ZipArchive();
    if ($archive->open($filePath) !== true) {
        throw new RuntimeException("The Excel workbook could not be opened.");
    }

    $uncompressedSize = 0;
    for ($index = 0; $index < $archive->numFiles; $index++) {
        $entry = $archive->statIndex($index);
        $uncompressedSize += $entry["size"] ?? 0;
        if ($uncompressedSize > 25 * 1024 * 1024) {
            $archive->close();
            throw new RuntimeException("The uncompressed Excel workbook is too large.");
        }
    }

    $workbook = loadSpreadsheetXml($archive, "xl/workbook.xml");
    $relationships = loadSpreadsheetXml($archive, "xl/_rels/workbook.xml.rels");
    $relationshipNamespace = "http://schemas.openxmlformats.org/officeDocument/2006/relationships";
    $sheetNodes = $workbook->sheets->children();
    if (!isset($sheetNodes->sheet[0])) {
        $archive->close();
        throw new RuntimeException("The Excel workbook has no worksheets.");
    }

    $sharedStrings = [];
    if ($archive->locateName("xl/sharedStrings.xml") !== false) {
        $sharedXml = loadSpreadsheetXml($archive, "xl/sharedStrings.xml");
        foreach ($sharedXml->si as $item) {
            $text = "";
            foreach ($item->xpath(".//*[local-name()='t']") ?: [] as $textNode) {
                $text .= (string) $textNode;
            }
            $sharedStrings[] = $text;
        }
    }

    $firstSheetRows = null;
    foreach ($sheetNodes->sheet as $sheet) {
        $relationshipId = (string) $sheet->attributes($relationshipNamespace)->id;
        $sheetTarget = "";
        foreach ($relationships->Relationship as $relationship) {
            if ((string) $relationship["Id"] === $relationshipId) {
                $sheetTarget = (string) $relationship["Target"];
                break;
            }
        }

        if ($sheetTarget === "") {
            continue;
        }

        $sheetPath = normalizeSpreadsheetPath($sheetTarget);
        if (strpos($sheetPath, "xl/") !== 0) {
            $sheetPath = "xl/" . $sheetPath;
        }

        $rows = spreadsheetXmlToRows(loadSpreadsheetXml($archive, $sheetPath), $sharedStrings);
        if ($firstSheetRows === null) {
            $firstSheetRows = $rows;
        }
        if (findProductImportHeader($rows) !== null) {
            $archive->close();
            return $rows;
        }
    }

    $archive->close();
    return $firstSheetRows ?? [];
}

function spreadsheetXmlToRows($sheetXml, $sharedStrings)
{
    $rows = [];
    foreach ($sheetXml->sheetData->row as $sheetRow) {
        $row = [];
        foreach ($sheetRow->c as $cell) {
            $reference = (string) $cell["r"];
            preg_match('/^[A-Z]+/', $reference, $columnLetters);
            $columnIndex = spreadsheetColumnIndex($columnLetters[0] ?? "A");
            $type = (string) $cell["t"];

            if ($type === "inlineStr") {
                $value = "";
                foreach ($cell->xpath(".//*[local-name()='t']") ?: [] as $textNode) {
                    $value .= (string) $textNode;
                }
            } else {
                $value = (string) $cell->v;
                if ($type === "s") {
                    $value = $sharedStrings[(int) $value] ?? "";
                }
            }
            $row[$columnIndex] = $value;
        }

        if ($row) {
            ksort($row);
            $lastColumn = max(array_keys($row));
            $rows[] = array_replace(array_fill(0, $lastColumn + 1, ""), $row);
        }
    }
    return $rows;
}

function loadSpreadsheetXml($archive, $path)
{
    $contents = $archive->getFromName($path);
    if ($contents === false) {
        $archive->close();
        throw new RuntimeException("The Excel workbook is missing a required worksheet file.");
    }

    $xml = simplexml_load_string($contents, "SimpleXMLElement", LIBXML_NONET);
    if ($xml === false) {
        $archive->close();
        throw new RuntimeException("The Excel workbook contains invalid XML.");
    }
    return $xml;
}

function normalizeSpreadsheetPath($path)
{
    $parts = [];
    foreach (explode("/", ltrim($path, "/")) as $part) {
        if ($part === "..") {
            array_pop($parts);
        } elseif ($part !== "" && $part !== ".") {
            $parts[] = $part;
        }
    }
    return implode("/", $parts);
}

function spreadsheetColumnIndex($letters)
{
    $index = 0;
    foreach (str_split($letters) as $letter) {
        $index = ($index * 26) + (ord($letter) - ord("A") + 1);
    }
    return max(0, $index - 1);
}
