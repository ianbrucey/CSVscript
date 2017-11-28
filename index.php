<?php

class CsvProcessor {

    public function __construct($pathToFile)
    {
        $this->fileData = fopen($pathToFile, 'r');
    }

    private $fileData;

    private $apiURL = "https://api.fixer.io/latest?symbols=USD,CAD";

    private function getCsvRow() {
        return fgetcsv($this->fileData, 4096,',');
    }

    private function convertToUSD($number) {
        $op = $number < 0 ? '- ' : '';
        return str_replace(['(',')'],'',$op . money_format('$%i',$number));
    }

    private function setColor($num)
    {
        return $num < 0 ? 'red' : 'green';
    }

    /** We use this method to hit the API that allows us to make the USD to CAD conversion */
    public function convertUsdToCad($amount) {
        $url = $this->apiURL;
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_URL => $url,
            CURLOPT_USERAGENT => 'localhost'
        ));
        $data = curl_exec($curl);
        $data = json_decode($data);
        $conversionRatio = $data->rates->CAD / $data->rates->USD;

        return round($conversionRatio * $amount,2);
    }

    public function csvToBootstrapHtmlTable()
    {
        $counter            = 0;
        $totalCost          = 0;
        $totalPrice         = 0;
        $totalQty           = 0;
        $totalProfitMargin  = 0;
        $headerColumns      = [];
        $addedHeaderColumns = ['Profit Margin', 'Total Profit (USD)', 'Total Profit (CAD)'];
        $footerColumns      = ['Average Price','Total QTY','Average Profit Margin','Total Profit (USD)','Total Profit (CAD)'];
        $table              = '<table class="table table-bordered">';
        $color              = '';


        while($data = $this->getCsvRow())
        {
            $table .= $counter == 0 ? '<thead>' : $counter == 1 ? '<tbody>' : '';
            $table .= '<tr>';
            $currentPrice = null;
            $currentCost  = null;
            $columnData   = null;
            $costKey      = null; // so that I can keep track of column no matter what order its in
            $priceKey     = null; // so that I can keep track of column no matter what order its in

            /** Sku, Cost, Price and Qty are set here */
            for($c = 0; $c < count($data); $c++)
            {
                $columnData = $data[$c];
                if($counter == 0) {
                    $headerColumns[$c] = $data[$c]; // set the first row of columns
                } else {
                    $color = $this->setColor($columnData);
                    switch ($headerColumns[$c]) {
                        case 'Cost':
                            $totalCost += $columnData;
                            $costKey = $c;
                            $columnData = $this->convertToUsd($columnData);
                            break;
                        case 'Price':
                            $totalPrice += $columnData;
                            $priceKey = $c;
                            $columnData = $this->convertToUsd($columnData);
                            break;
                        case 'QTY':
                            $totalQty += $columnData;
                            break;
                        default:
                            break;
                    }
                }

                $table .= $counter == 0 ? sprintf('<th>%s</th>',$columnData) : sprintf('<td style="color: %s;">%s</td>',$color,$columnData);
            }

            /**
             * Last 3 columns: Profit Margin and Totals are set here.
             * These columns are not given in the file and must be calculated on the fly
             */
            if($counter > 0) {
                $currentPrice = $data[$priceKey];
                $currentCost  = $data[$costKey];
                for($i = 0; $i < count($addedHeaderColumns); $i++)
                {
                    switch ($addedHeaderColumns[$i]) {
                        case 'Profit Margin':
                            $totalProfitMargin = (($currentPrice - $currentCost) / $currentCost);
                            $color = $this->setColor($totalProfitMargin);
                            $columnData = ($totalProfitMargin * 100) . '%';
                            break;
                        case 'Total Profit (USD)':
                            $color = $this->setColor($currentPrice - $currentCost);
                            $columnData = $this->convertToUsd(($currentPrice - $currentCost) * 5);
                            break;
                        case 'Total Profit (CAD)':
                            $color = $this->setColor($currentPrice - $currentCost);
                            $columnData = $this->convertUsdToCad(($currentPrice - $currentCost) * 5);
                            break;
                        default;
                            break;
                    }

                    $table .= sprintf('<td style="color: %s;">%s</td>',$color,$columnData);
                }
            }

            $table .= '</tr>';
            $table .= $counter == 0 ? '</thead>' : '';
            ++$counter;
        }

        /** Footer columns are added here */
        $table .= '<tfoot>';
        $table .= '<tr>';
        foreach ($footerColumns as $column)
        {
            $table .= sprintf('<th>%s</th>', $column);
        }
        $table .= '</tr>';
        $table .= sprintf('<td>%s</td>',$this->convertToUSD($totalPrice / ($counter - 1)) ); // average price
        $table .= sprintf('<td>%s</td>',$totalQty); // total quantity
        $table .= sprintf('<td>%s%%</td>',round(($totalPrice - $totalCost) / $totalCost * 100,2));
        $table .= sprintf('<td>%s</td>',$this->convertToUSD(($totalPrice - $totalCost) * $totalQty));
        $table .= sprintf('<td>%s</td>',$this->convertUsdToCad(($totalPrice - $totalCost) * $totalQty));
        $table .= '</tfoot>';

        $table .= '</tbody></table>';

        return $table;
    }
}

$bootstrapTable = new CsvProcessor('test.csv');

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>CSV Test</title>
    <link rel="stylesheet" href="css/styles.css">
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.1.1/jquery.min.js"></script>
</head>

<body id="page-top">


<div class="container-fluid">
    <div class="row">
        <div class="col-md-12 text-center" id="logoDiv"></div>
        <div class="col-md-8 col-md-offset-2 btn btn-primary">
            <h2 class="text-default text-center">
                CSV TEST
            </h2>
        </div>
    </div>
</div>

<?= $bootstrapTable->csvToBootstrapHtmlTable(); ?>


</body>
<script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>
</html>






