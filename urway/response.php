<?php


 function result()
	{
	    
	    echo '
	    <!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">

<head>

    <title>Invoice</title>
 <link rel=stylesheet type=text/css href= style.css />
</head>

<body>
    <div id="page-wrap">
        <textarea id="header">INVOICE</textarea>
        <div id="identity">
            <label id="address">
                SAUDI ARABIA
            </label>

            <!--div id="logo">
                <img src="https://urway.sa/wp-content/uploads/2019/06/Logo-300x150.png" alt="logo" />
            </div-->

        </div>

        <div style="clear:both"></div>

        <div id="customer">

            <label id="customer-title">Sample Receipt
</label>

            <table id="meta">
                <tr>
                    <td class="meta-head">Payment id #</td>
                    <td><textarea>'.$_GET['PaymentId'].'</textarea></td>
                </tr>
                <tr>
                    <td class="meta-head">Result</td>
                    <td>
                        <div class="due" style="color:white ; background-color: green;">'.$_GET['Result'].'</div>
                    </td>
                </tr>
                <tr>
                    <td class="meta-head">Response Code</td>
                    <td>
                        <div class="due">'.$_GET['ResponseCode'].'</div>
                    </td>
                </tr>
                <tr>
                    <td class="meta-head">Auth Code</td>
                    <td>
                        <div class="due">'.$_GET['AuthCode'].'</div>
                    </td>
                </tr>
                <tr>
                    <td class="meta-head">Date</td>
                    <td><textarea id="date">'.date('d-m-Y H:i:s a').'</textarea></td>
                </tr>
                <tr>
                    <td class="meta-head">CardBrand</td>
                    <td><textarea id="date">'.$_GET['cardBrand'].' </textarea></td>
                </tr>
                

            </table>

        </div>

        <table id="items">

            <tr>
                <th>Item</th>
                <th>Description</th>
                <!--th>Unit Cost</th>
                <th>Quantity</th-->
                <th>Price</th>
            </tr>

            <tr class="item-row">
                <td class="item-name">
                    <div><label><?php echo Sample Item ?></label></div>
                </td>
                <td class="description"><label></label></td>
          
                <td colspan="3"><span class="price">'.$_GET['amount'].'</span></td>
            </tr>
            <tr>
                <td colspan="2" class="blank"> </td>
                <td colspan="1" class="total-line">Amount Paid</td>

                <td class="total-value"><textarea id="paid">'.$_GET['amount'].'</textarea></td>
            </tr>
        </table>

        <div id="terms">
            <!--a href="/urshop/" class="">Back to Store</a-->
        </div>

    </div>

</body>

</html>
	    ';
	    
	}



$terminalId = "";// Will be provided by URWAY
$password = "";// Will be provided by URWAY
$key = "";// Will be provided by URWAY

if ($_GET !== NULL) {
    $requestHash = "" . $_GET['TranId'] . "|" . $key . "|" . $_GET['ResponseCode'] . "|" . $_GET['amount'] . "";
	$txn_details1 = "" . $_GET['TrackId'] . "|" . $terminalId . "|" . $password . "|" . $key . "|" . $_GET['amount'] . "|SAR";
	
    
    $hash = hash('sha256', $requestHash);
//	$hash1 = hash('sha256', $txn_details1);
   // echo $hash1; die;
    if ($hash === $_GET['responseHash']) {
        
        
        $txn_details1 = "" . $_GET['TrackId'] . "|" . $terminalId . "|" . $password . "|" . $key . "|" . $_GET['amount'] . "|SAR";
        //Secure check
        $requestHash1 = hash('sha256', $txn_details1);
        $apifields    = array(
            'trackid' => $_GET['TrackId'],
            'terminalId' => $terminalId,
            'action' => '10',
            'merchantIp' => "",
            'password' => $password,
            'currency' => "SAR",
             'transid' => "",
			'transid' => $_GET['TranId'],
            'amount' => $_GET['amount'],
            'udf5' => "",
            'udf3' => "",
            'udf4' => "",
            'udf1' => "",
            'udf2' => "",
            'requestHash' => $requestHash1
        );
        
        
        $apifields_string = json_encode($apifields);
        
        $url = "https://payments-dev.urway-tech.com/URWAYPGService/transaction/jsonProcess/JSONrequest";
        $ch  = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $apifields_string);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Content-Length: ' . strlen($apifields_string)
        ));
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        
        //execute post
        $apiresult = curl_exec($ch);
     // print_r($apiresult);die;
        $urldecodeapi        = (json_decode($apiresult, true));
        $inquiryResponsecode = $urldecodeapi['responseCode'];
        $inquirystatus       = $urldecodeapi['result'];
        
        
        
        if ($_GET['Result'] === 'Successful'  && $_GET['ResponseCode']==='000') {
            
            if($inquirystatus=='Successful' || $inquiryResponsecode=='000'){
        $trackid = $_GET['TrackId'];
		$responseCode = $_GET['ResponseCode'];
		$amount = $_GET['amount'];
result();
}else {
            echo "Something went wrong!!! Secure Check failed!!!!!!!";
        }
            
        } else {
           result();
        }
    } else {
        echo "Hash Mismatch!!!!!!!";
		
    }
} else {
    
    echo "Something Went wrong!!!!!!!!!!!!";
}

?>