<?php

/** Insert Orimi customers*/

$app->post('/import_customers_from_file', 'authenticate', function() use ($app) {

    global $user_id;
    permissionFabricantAdmin($user_id);

    //-------------------Берем Excel файл----------------------------

    if (!isset($_FILES["file"])) {
        throw new Exception('Param file is missing');
    }

    // Check if the file is missing
    if (!isset($_FILES["file"]["name"])) {
        throw new Exception('Property name of file param is missing');
    }

    // Check the file size > 100MB
    if($_FILES["file"]["size"] > 100*1024*1024) {
        throw new Exception('File is too big');
    }

    $tmpFile = $_FILES["file"]["tmp_name"];

    $ext = pathinfo($_FILES["file"]["name"], PATHINFO_EXTENSION);
    $filename = date('dmY').'-'.uniqid('excel_for_import_customers-').$ext;

    /** NEED DELETE /FB **/

    $filepath = $_SERVER["DOCUMENT_ROOT"].'/fb/v2/reports/'.$filename;

    // Подключаем класс для работы с excel
    require_once dirname(__FILE__).'/../libs/PHPExcel/PHPExcel.php';

    // Подключаем класс для вывода данных в формате excel
    require_once dirname(__FILE__).'/../libs/PHPExcel/PHPExcel/IOFactory.php';

    //$objPHPExcel = PHPExcel_IOFactory::load($path);

    $inputFileType = PHPExcel_IOFactory::identify($tmpFile);  // узнаем тип файла, excel может хранить файлы в разных форматах, xls, xlsx и другие
    $objReader = PHPExcel_IOFactory::createReader($inputFileType); // создаем объект для чтения файла
    $objPHPExcel = $objReader->load($tmpFile); // загружаем данные файла в объект
    
    // Set and get active sheet
    $objPHPExcel->setActiveSheetIndex(0);
    $worksheet = $objPHPExcel->getActiveSheet();
    $worksheetTitle = $worksheet->getTitle();
    $highestRow = $worksheet->getHighestRow();
    $highestColumn = $worksheet->getHighestColumn();
    $highestColumnIndex = PHPExcel_Cell::columnIndexFromString($highestColumn);
    $nrColumns = ord($highestColumn) - 64;

    $db_profile = new DbHandlerProfile();
    $db_fabricant = new DbHandlerFabricant();

    $logs = array();
    for ($rowIndex = 2; $rowIndex <= $highestRow; ++$rowIndex) {
        $customer = array();

        $cells = array();

        for ($colIndex = 0; $colIndex < $highestColumnIndex; ++$colIndex) {
            $cell = $worksheet->getCellByColumnAndRow($colIndex, $rowIndex);
            $cells[] = $cell->getValue();
        }

        $uid = $cells[0];
        $name = $cells[1];
        $name_full = $cells[2];
        $phone = $cells[3];
        $address = $cells[4];
        $contractorid = intval($cells[5]);
        $userid = intval($cells[6]);
        $status = 1;
        $type = 1;
        $log = array();
        $log[] = $rowIndex - 1;

        if (trim($name) === '' || trim($address) === '') {
            $log[] = "Name or address of customer not setted.";
        } else {
            $infoInit = '{"name":{"text":""},"name_full":{"text":""},"summary":{"text":""},"icon":{"image_url":""},"tags":[],"details":[{"type":2,"slides":[{"photo":{"image_url":""},"title":{"text":""}}]}]}';
            $info = json_decode($infoInit, true);
            $info["name"]["text"] = $name;
            $info["name_full"]["text"] = $name_full;

            $new_id = $db_profile->createGroupWeb($name, $address, $phone, $status, $type, json_encode($info, JSON_UNESCAPED_UNICODE));
    
            if($new_id != NULL) {
                $log[] = "Customer created with id=".$new_id.".";
                if ($userid > 0) {
                    $db_profile->addUserToGroup($new_id, $userid, $status);
                    $log[] = "User with id=".$userid." added to created group.";
                }
                if ($contractorid > 0 && isUID($uid)) {
                    $db_crm = new DbHandlerCRM();
                    $customerCodeSetResult = $db_crm->setCustomerCodeInContractor($new_id, $uid, $contractorid);
                    if ($customerCodeSetResult) {
                        $log[] = "Customer code setted.";
                    } else {
                        $log[] = "Error on set customer code.";
                    }
                }
                
            } else {
                $log[] = "Customer not created.";
            }
        }
        $logs[] = $log;
    }

    $response = array();
    $response["error"] = false;
    $response["success"] = 1;
    $response["log"] = $logs;

    echoResponse(200,$response);
});

$app->post('/excel_translate_with_col_name', function() use ($app) {
    global $user_id;
    //permissionFabricantAdmin($user_id);

    //-------------------Берем Excel файл----------------------------

    try {
        if (!isset($_FILES["file"])) {
            throw new Exception('Param file is missing');
        }

        // Check if the file is missing
        if (!isset($_FILES["file"]["name"])) {
            throw new Exception('Property name of file param is missing');
        }

        // Check the file size > 10MB
        if($_FILES["file"]["size"] > 10*1024*1024) {
            throw new Exception('File is too big');
        }

        $tmpFile = $_FILES["file"]["tmp_name"];

        $ext = pathinfo($_FILES["file"]["name"], PATHINFO_EXTENSION);
        $filename = date('dmY-His').'-'.$_FILES["file"]["name"];

        $filepath = $_SERVER["DOCUMENT_ROOT"].'/v2/reports/'.$filename;

        $inputFileType = PHPExcel_IOFactory::identify($tmpFile);  
        $objPHPExcel = new PHPExcel();
        $objReader = PHPExcel_IOFactory::createReader($inputFileType);
        $objReader->setReadDataOnly(true);

        try {
            /** Load $inputFileName to a PHPExcel Object  **/
            $objPHPExcel = $objReader->load($tmpFile);
        } catch(PHPExcel_Reader_Exception $e) {
            die('Error loading file: '.$e->getMessage());
        }
        
        // Set and get active sheet
        $objPHPExcel->setActiveSheetIndex(0);
        $worksheet = $objPHPExcel->getActiveSheet();
        $resultWorksheet = new PHPExcel_Worksheet($objPHPExcel, 'Results');

        $highestRow = $worksheet->getHighestRow();
        $highestColumn = $worksheet->getHighestColumn();
        $highestColumnIndex = PHPExcel_Cell::columnIndexFromString($highestColumn);

        $columnNames = array();
        for ($colIndex = 0; $colIndex <= $highestColumnIndex; ++$colIndex) {
            $cell = $worksheet->getCellByColumnAndRow($colIndex, 1);
            $columnNames[] = $cell->getColumn();
        }

        $rows = array();
        for ($rowIndex = 1; $rowIndex <= $highestRow; ++$rowIndex) {
            $cells = array();
            for ($colIndex = 0; $colIndex <= $highestColumnIndex; ++$colIndex) {
                $cell = $worksheet->getCellByColumnAndRow($colIndex, $rowIndex);
                $cells[] = $cell->getValue();
            }
            $rows[] = $cells;
        }

        foreach ($columnNames as $colIndex => $colName) {
            $resultWorksheet->setCellValueByColumnAndRow(0, $colIndex + 1, $colName);
        }

        foreach ($rows as $rowIndex => $row) {
            foreach ($row as $colIndex => $cell) {
                $resultWorksheet->setCellValueByColumnAndRow($rowIndex + 1, $colIndex + 1, $cell);
            }
        }

        $objPHPExcel->addSheet($resultWorksheet);

        $objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, $inputFileType);
        $objWriter->setPreCalculateFormulas(false);
        $objWriter->save($filepath);

        $response = array();
        $response["error"] = false;
        $response["success"] = 1;
        $response["fileName"] = $columnNames;
    }
    catch (Exception $e) {
        // Exception occurred. Make error flag true
        $response["error"] = true;
        $response["message"] = $e->getMessage();
    }

    echoResponse(200,$response);
});

$app->post('/kustuk_massive', function() use ($app) {
    global $user_id;
    $startTime = time();
    //permissionFabricantAdmin($user_id);

    //-------------------Берем Excel файл----------------------------
    try {
        try {
            checkUploadFiles(array("massive", "articles", "base"));
        } catch (UploadFileException $e) {
            throw $e;
        }
        $massiveTmpFile = $_FILES["massive"]["tmp_name"];
        $articlesTmpFile = $_FILES["articles"]["tmp_name"];
        $baseTmpFile = $_FILES["base"]["tmp_name"];
        
        // настройки PHPExcel (на хабре еаписали, что помогает при работе с большими файлами)
        // https://habrahabr.ru/post/148203/
        $cacheMethod = PHPExcel_CachedObjectStorageFactory::cache_to_phpTemp;
        $cacheSettings = array( 'memoryCacheSize ' => '256MB');
        PHPExcel_Settings::setCacheStorageMethod($cacheMethod, $cacheSettings);
        // чтение файла с артикулами
        /*$inputFileType = PHPExcel_IOFactory::identify($articlesTmpFile);
        $articlesReader = PHPExcel_IOFactory::createReader($inputFileType);
        $articlesReader->setReadDataOnly(true);
        $articlesPHPExcel = $articlesReader->load($articlesTmpFile);
        $articlesPHPExcel->setActiveSheetIndex(0);
        $articlesWorksheet = $articlesPHPExcel->getActiveSheet();

        $articlesHighestRow = $articlesWorksheet->getHighestRow();
        $articlesHighestColumn = $articlesWorksheet->getHighestColumn();
        $articlesHighestColumnIndex = PHPExcel_Cell::columnIndexFromString($articlesHighestColumn);
        // массив с названиями колонок newform
        $newformColumnNames = array();
        // массив с артикулами, вида [ "AA" => ["0000-00" => count] ]
        $articles = array();
        for ($rowIndex = 2; $rowIndex <= $articlesHighestRow; ++$rowIndex) {
            $columnName = $articlesWorksheet->getCellByColumnAndRow(0, $rowIndex)->getValue();
            $newformColumnNames[$columnName] = $articlesWorksheet->getCellByColumnAndRow(1, $rowIndex)->getValue();
            $articles_items = array();
            for ($colIndex = 3; $colIndex < $articlesHighestColumnIndex; ++$colIndex) {
                $cellValue = trim($articlesWorksheet->getCellByColumnAndRow($colIndex, $rowIndex)->getValue());
                if (!empty($cellValue) && !in_array($cellValue, $articles_items)) {
                    $articles_items[] = $cellValue;
                }
            }
            if (count($articles_items) > 0) {
                $articles[$columnName] = $articles_items;
            }
        }
        unset($articlesReader);
        unset($articlesPHPExcel);*/

        // чтение файла базы
        // названия столбцов для чтения из базы и индексы создаваемого массива
        // 0 - F - code
        // 1 - L - namett
        // 2 - M - adrestt
        // 3 - N - typett
        // 4 - BS - active

        /*$baseReadSheet = 'Общая база розничных т_т_на сен';
        $baseStartRow = 10;
        $inputFileType = PHPExcel_IOFactory::identify($baseTmpFile);
        $baseReader = PHPExcel_IOFactory::createReader($inputFileType);
        $baseReader->setReadDataOnly(true);
        $baseReader->setLoadSheetsOnly($baseReadSheet);
        $basePHPExcel = $baseReader->load($baseTmpFile);
        $baseWorksheet = $basePHPExcel->getSheetByName($baseReadSheet);

        $baseHighestRow = $baseWorksheet->getHighestRow();
        $baseHighestColumn = $baseWorksheet->getHighestColumn();
        $baseHighestColumnIndex = PHPExcel_Cell::columnIndexFromString($baseHighestColumn);
        $baseHighestColumn++;
        // массив с магазинами
        $checkCustomers = array();
        // последний заполненный столбец
        $activeColName = 'BS';
        // поиск последнего заполненного столбеца
        for ($colIndex = 'O'; $colIndex != $baseHighestColumn; ++$colIndex) {
            $notEmptyColName = trim($baseWorksheet->getCell($colIndex.'8')->getValue());
            if (!empty($notEmptyColName)) {
                $activeColName = $colIndex;
            }
        }
        for ($rowIndex = $baseStartRow; $rowIndex <= $baseHighestRow; ++$rowIndex) {
            $rowCustomerActivity = trim($baseWorksheet->getCell($activeColName.$rowIndex)->getValue());
            if (intval($rowCustomerActivity) === 1) {
                $rowCustomerCode = trim($baseWorksheet->getCell('F'.$rowIndex)->getValue());
                $rowCustomerName = $baseWorksheet->getCell('L'.$rowIndex)->getValue();
                $rowCustomerAddress = $baseWorksheet->getCell('M'.$rowIndex)->getValue();
                $rowCustomerType = $baseWorksheet->getCell('N'.$rowIndex)->getValue();
                $checkCustomers[$rowCustomerCode] = [
                    'name' => $rowCustomerName,
                    'address' => $rowCustomerAddress,
                    'type' => $rowCustomerType
                ];
            }
        }
        unset($baseReader);
        unset($basePHPExcel);*/

        // сгруппированный массив по названию и адресу точки
        $tradePoints = array();
        $chunkSize = 2048;
        // названия столбцов для чтения из массива и индексы создаваемого массива
        // 0 - e - tp
        // 1 - f - kodtt
        // 2 - k - namett
        // 3 - l - adrestt
        // 4 - n - nndok
        // 5 - o - datadok
        // 6 - t - kodtov
        // 7 - v - astr
        // 8 - w - cat
        // 9 - x - rbp
        // 10 - y - Saleunit
        // 11 - z - Salerur
        // 12 - aa - Salekg
        // 13 - ab - sum_salerur
        $massiveReadColumns = array(
            //'E', 'F', 'K', 'L', 'N', 'O', 'T', 'V', 'W', 'X', 'Y', 'Z', 'AA', 'AB'
            'E', 'F', 'K', 'L', 'T'
        );
        $massiveStartRow = 2;

        //while ($massiveStartRow <= 2) {
            $massiveFilter = new massiveChunkReadFilter($massiveReadColumns);

            $inputFileType = PHPExcel_IOFactory::identify($massiveTmpFile);
            $massiveReader = PHPExcel_IOFactory::createReader($inputFileType);
            $massiveReader->setReadFilter($massiveFilter);
            $massiveReader->setReadDataOnly(true);

            // New PHPExcel class
            //$newformFile = new PHPExcel();
            // Set and get active sheet
            //$newformFile->setActiveSheetIndex(0);
            //$newformWorksheet = $newformFile->getActiveSheet();
            // Sheet title
            //$newformWorksheet->setTitle('Данные для newform');
            //65536
            for ($startRow = 2; $startRow <= 65536; $startRow += $chunkSize) {
                $massiveFilter->setRows($startRow, $chunkSize); 
                $massivePHPExcel = $massiveReader->load($massiveTmpFile); 
                $massivePHPExcel->setActiveSheetIndex(0);
                //    Do some processing here 
                for ($rowIndex = $startRow; $rowIndex < $startRow + $chunkSize; ++$rowIndex) {
                    $rowTradePointCode = trim($massivePHPExcel->getActiveSheet()->getCell('F'.$rowIndex)->getValue());
                    //$rowItemArticle = trim($massiveWorksheet->getCell('T'.$rowIndex)->getValue());
                    
                    //$rowTradePointCode = trim($massiveWorksheet->getCell('F'.$rowIndex)->getValue());
                    $tradePoints[] = $rowTradePointCode;
                }
                //$massiveStartRow += $chunkSize;
                //unset($massiveReader);
                unset($massivePHPExcel);
            //}
            }

            //$massivePHPExcel = $massiveReader->load($massiveTmpFile);
            //$massivePHPExcel->setActiveSheetIndex(0);
            //$massiveWorksheet = $massivePHPExcel->getActiveSheet();

            //$massiveHighestRow = $massiveWorksheet->getHighestRow();
            //$massiveHighestColumn = $massiveWorksheet->getHighestColumn();
            //$massiveHighestColumnIndex = PHPExcel_Cell::columnIndexFromString($massiveHighestColumn);
            //$massiveHighestColumn++;
            
            //for ($rowIndex = $massiveStartRow; $rowIndex < $massiveStartRow + $chunkSize; ++$rowIndex) {
                /*$rowTradePointCode = trim($massiveWorksheet->getCell('F'.$rowIndex)->getValue());
                $rowItemArticle = trim($massiveWorksheet->getCell('T'.$rowIndex)->getValue());
                if (!isset($tradePoints[$rowTradePointCode])) {
                    $rowTradePointName = $massiveWorksheet->getCell('K'.$rowIndex)->getValue();
                    $rowTradePointAddress = $massiveWorksheet->getCell('L'.$rowIndex)->getValue();
                    // если наименование или адрес пустые, пропускаем
                    if (!empty($rowTradePointName) && !empty($rowTradePointAddress)) {
                        $tradePoints[$rowTradePointCode] = array();
                        $tradePoints[$rowTradePointCode]['name'] = $rowTradePointName;
                        $tradePoints[$rowTradePointCode]['address'] = $rowTradePointAddress;
                        $tradePoints[$rowTradePointCode]['items'] = array();
                        foreach ($articles as $colName => $colArticles) {
                            if (in_array($rowItemArticle, $colArticles)) {
                                $tradePoints[$rowTradePointCode]['items'][$colName][$rowItemArticle] = 1;
                                break;
                            }
                        }
                    }
                } else {
                    foreach ($articles as $colName => $colArticles) {
                        if (in_array($rowItemArticle, $colArticles)) {
                            $tradePoints[$rowTradePointCode]['items'][$colName][$rowItemArticle] = 1;
                            break;
                        }
                    }
                }*/
                //$rowTradePointCode = trim($massiveWorksheet->getCell('F'.$rowIndex)->getValue());
                //$tradePoints[] = $rowTradePointCode;
            //}
            //$massiveStartRow += $chunkSize;
            //unset($massiveReader);
            //unset($massivePHPExcel);
        //}

        /*$reader = ReaderFactory::create(Type::XLSX);
        $reader->open($massiveTmpFile);
        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $tradePoints[] = $row[0];
            }
        }
        $reader->close();*/

        // суммируем знаения по колонке
        /*foreach ($tradePoints as $tradePointKey => $tradePoint) {
            foreach ($tradePoint["items"] as $colName => $colArticles) {
                $summ = 0;
                foreach ($colArticles as $article => $count) {
                    $summ += $count;
                }
                if ($summ > 0) {
                    $tradePoints[$tradePointKey]["items"][$colName] = $summ;
                } else {
                    unset($tradePoints[$tradePointKey]["items"][$colName]);
                }
            }
        }*/

        $newformFileName = date('dmY-').uniqid('newform-data-').'.xls';
        $newformFileNamePath = $_SERVER["DOCUMENT_ROOT"].'/v2/reports/'.$newformFileName;

        // New PHPExcel class
        $newformFile = new PHPExcel();
        // Set and get active sheet
        $newformFile->setActiveSheetIndex(0);
        $newformWorksheet = $newformFile->getActiveSheet();
        // Sheet title
        $newformWorksheet->setTitle('Данные для newform');

        /*$newformWorksheet->setCellValue('I1', 'Название торговой точки');
        $newformWorksheet->setCellValue('J1', 'Адрес');
        foreach ($newformColumnNames as $colIndex => $colName) {
            $newformWorksheet->setCellValue($colIndex.'1', $colName);
        }

        $rowIndex = 2;
        foreach ($tradePoints as $row => $tradePoint) {
            $newformWorksheet->setCellValue('I'.$rowIndex, $tradePoint["name"]);
            $newformWorksheet->setCellValue('J'.$rowIndex, $tradePoint["address"]);
            foreach ($tradePoint["items"] as $colIndex => $count) {
                $newformWorksheet->setCellValue($colIndex.$rowIndex, $count);
            }
            $rowIndex++;
        }*/
        /*$rowIndex = 1;
        foreach ($tradePoints as $key => $value) {
            $newformWorksheet->setCellValue('A'.$rowIndex, $value);
            $rowIndex++;
        }*/

        

        // We'll be outputting an excel file
        //header('Content-type: application/vnd.ms-excel');
        // It will be called file.xls
        //header('Content-Disposition: attachment; filename="'.$newformFileName.'"');
        //$objWriter = new PHPExcel_Writer_Excel5($newformFile);
        // Write file to the browser
        //$objWriter->save('php://output');

        $objWriter = new PHPExcel_Writer_Excel5($newformFile);
        $objWriter->save($newformFileNamePath);

        

        $response = array();
        $response["error"] = false;
        //$response["success"] = $tradePoints;
        $response["exec"] = date('i:s', time() - $startTime);
        //$response["newformFileNamePath"] = $checkCustomers;
    }
    catch (Exception $e) {
        // Exception occurred. Make error flag true
        $response["error"] = true;
        $response["message"] = $e->getMessage();
    }

    echoResponse(200,$response);
});

class excelReadFilterByColumn implements PHPExcel_Reader_IReadFilter  {
    private $_columns  = array();

    /**  Get the list of rows and columns to read  */
    public function __construct($columns) {
        $this->_columns  = $columns;
    }

    public function readCell($column, $row, $worksheetName = '') {
        //  Only read the rows and columns that were configured
        if (in_array($column, $this->_columns)) {
            return true;
        }
        return false;
    }
}

class excelReadFilterByStartRow implements PHPExcel_Reader_IReadFilter  {
    private $_columns  = array();

    /**  Get the list of rows and columns to read  */
    public function __construct($startRow) {
        $this->_startRow  = $startRow;
    }

    public function readCell($column, $row, $worksheetName = '') {
        //  Only read the rows and columns that were configured
        if ($row >= $this->$startRow) {
            return true;
        }
        return false;
    }
}

class chunkReadFilter implements PHPExcel_Reader_IReadFilter {
    private $_startRow = 0;
    private $_endRow = 0;

    public function setRows($startRow, $chunkSize) {
        $this->_startRow    = $startRow;
        $this->_endRow      = $startRow + $chunkSize;
    }

    public function readCell($column, $row, $worksheetName = '') {
        if (($row == 1) || ($row >= $this->_startRow && $row < $this->_endRow)) {
            return true;
        }
        return false;
    }
}
class massiveChunkReadFilter implements PHPExcel_Reader_IReadFilter {
    private $_startRow = 0;
    private $_endRow = 0;
    private $_columns  = array();

    public function __construct($columns) {
        $this->_columns  = $columns;
    }

    public function setRows($startRow, $chunkSize) {
        $this->_startRow = $startRow;
        $this->_endRow = $startRow + $chunkSize;
    }

    public function readCell($column, $row, $worksheetName = '') {
        if (($row == 1) || ($row >= $this->_startRow && $row < $this->_endRow && in_array($column, $this->_columns))) {
            return true;
        }
        return false;
    }
}

class UploadFileException extends Exception {}

function checkUploadFiles($filenames) {
    if (is_array($filenames)) {
        foreach ($filenames as $filename) {
            checkUploadFile($filename);
        }
    } else {
        checkUploadFile($filenames);
    }
}

function checkUploadFile($filename) {
    if (!isset($_FILES[$filename])) {
        throw new UploadFileException('File '.$filename.' is missing');
    }

    // Check if the file is missing
    if (!isset($_FILES[$filename]["name"])) {
        throw new UploadFileException('Property name of file '.$filename.' is missing');
    }

    // Check the file size > 100MB
    if($_FILES[$filename]["size"] > 100*1024*1024) {
        throw new UploadFileException('File '.$filename.' is too big');
    }
}

//require('/../libs/spreadsheet-reader-master/php-excel-reader/excel_reader2.php');

$app->post('/spout_test', function() use ($app) {
    $startTime = time();

    $massiveTmpFile = $_FILES["massive"]["tmp_name"];
    $reader = ReaderFactory::create(Type::XLSX);
    $reader->open($massiveTmpFile);

    $rows = array();
    foreach ($reader->getSheetIterator() as $sheet) {
        foreach ($sheet->getRowIterator() as $row) {
            // do stuff with the row
            $rows[] = $row[1];
        }
    }

    $reader->close();

    $response = array();
    $response["error"] = false;
    $response["result"] = $rows;
    $response["exec"] = date('i:s', time() - $startTime);
    //$response["newformFileNamePath"] = $checkCustomers;
    echoResponse(200, $response);
});

//require_once dirname(__FILE__).'/../libs/SpreadsheetReader/php-excel-reader/excel_reader2.php';

$app->post('/xlsreader2', function() use ($app) {
    $startTime = time();
    require_once dirname(__FILE__).'/../libs/SpreadsheetReader/SpreadsheetReader.php';

    $massiveTmpFile = $_FILES["massive"]["tmp_name"];
    $newformFileNamePath = $_SERVER["DOCUMENT_ROOT"].'/v2/reports/log.xlsx';

    $writer = WriterFactory::create(Type::XLSX);
    $writer->openToFile($newformFileNamePath);
    $writer->getCurrentSheet();

    $rows = array();
    $Reader = new SpreadsheetReader($_SERVER["DOCUMENT_ROOT"].'/v2/reports/14081310.xlsx');
    foreach ($Reader as $Row) {
        //$rows[] = $Row;
        $writer->addRow($Row);
    }

    
    //$writer->addRows($rows);
    $writer->close();

    $response = array();
    $response["error"] = false;
    //$response["rows"] = $rows;
    $response["exec"] = date('i:s', time() - $startTime);
    //$response["newformFileNamePath"] = $checkCustomers;
    echoResponse(200, $response);
});

?>