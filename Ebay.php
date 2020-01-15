<?php

ini_set('max_execution_time', 0);
/* * ******************************************************************************
 * ebay 接口类 *
 * ***************************************************************************** */
require('./xmlhandle.php');
require('./eBaySession.php');
class Ebay {

    public $devID;
    public $appID;
    public $certID;
    public $serverUrl;
    public $siteID;
    public $detailLevel;
    public $compatabilityLevel;
    public $runame;
    public $token;

    public function __construct($token = 'AgAAAA**AQAAAA**aAAAAA**AogXVg**nY+sHZ2PrBmdj6wVnY+sEZ2PrA2dj6AEloCiCpSBpgydj6x9nY+seQ**RAMCAA**AAMAAA**1lI7qyVbkdrN4YZ6Rgypuq9QdoIzp1n6DfRQgPvof7TBtjVeSog7hoq9H+CseTvLxfu/TfpMqRTHMhIYIyUHlMlROaS5lo6XLuPzLY0APNUmecJRHA8iiZfm5f+oXONkr8elVU0XjimopqzvOJ96yblrzXT30OzdmZBmgJzb8iJtvcr/CPshKBGRsgxI/MsigdbzrnCsRgwsjcGVCOxv5baBRtDl7/Uu9RHPN/lKxQIL0UJAxe4AzDyr0aAnWZh1a9GdeiU0X0+sjObcsPTVQ1cYukU4IVtFMwm63AZsTeXAPbedV7tJUSbPG/XTTkjN0xpzP9eiN9i0DB31uAho+B1oz4PwEBDDDYXzfuRGolG9JIwlYvSUqVwPfB11AbalX/nakogUHVxDr5sGQGlzZ/jjvceDwnVSnzI7HuilYa1ZOcs6rA/H+Dv84dOGI3zRNsJ3vC+bvEDm8GmF2NH5Fx+hv1V4bCBVU2zX0FGR0n31meH8iHGghAmse/3+9eDNmx5uqQvicwj37q6LfbtJ8msJPhgOgl8DodrdVH1eDteo8dReSqvBIpZZZF/ah+4feO/m1kDAKVL6BrmnKLzc13SOgjKPJH+LDgSUjdKjzpJf0dR49X56GL6buvjeyfMO24Jl+1rg8ZEAvJqK7ZKGwzAcjM+lfWLpPCt+oDAtMRh2cJXU9F6Hop/2w2OK7KxIxiic1uW2ccxcNE0k5XVfteRVz7ZMZfWjx6Zb3dUgzHGVGSRp2cbmxHPLTozFKHdF', $site = 0) {
        $this->devID = Yii::app()->params['ebay_developer']['devid'];
        $this->appID = Yii::app()->params['ebay_developer']['appid'];
        $this->certID = Yii::app()->params['ebay_developer']['certid'];
        $this->serverUrl = "https://api.ebay.com/ws/api.dll";
        $this->siteID = $site;
        $this->detailLevel = 0;
        $this->compatabilityLevel = 551;
        $this->runame = Yii::app()->params['ebay_developer']['runame'];
        //任意获得验证合法的token
        $this->token = $token;
    }

    /* 取得ebay 的session ID */

    public function getSessionID($token) {

        $verb = 'GetSessionID';
        $requestXmlBody = '<?xml version="1.0" encoding="utf-8"?>
						  <GetSessionIDRequest xmlns="urn:ebay:apis:eBLBaseComponents">
						  <RuName>' . $this->runame . '</RuName>
						  </GetSessionIDRequest>';

        $session = new eBaySession($token, $this->devID, $this->appID, $this->certID, $this->serverUrl, $this->compatabilityLevel, $this->siteID, $verb);
        $responseXml = $session->sendHttpRequest($requestXmlBody);
        $data = XML_unserialize($responseXml);
        //		$responseDoc = new DomDocument();
        //		$responseDoc->loadXML($responseXml);
        //		$errors = $responseDoc->getElementsByTagName('Errors');
        //
		$data = XML_unserialize($responseXml);
        $getdata = $data['GetSessionIDResponse'];

        $this->insertEbayApiLog($verb, $requestXmlBody, $getdata); //获得ebayaip日志

        $sessionid = @$getdata['SessionID'];

        return $sessionid;
    }

    //取得ebay的token
    public function getToken($sessionid) {

        $verb = 'FetchToken';
        $userToken = '';
        $requestxml = '<?xml version="1.0" encoding="utf-8"?>
		<FetchTokenRequest xmlns="urn:ebay:apis:eBLBaseComponents"> 
		<RequesterCredentials> 
		</RequesterCredentials> 
		<SessionID>' . $sessionid . '</SessionID> 
		</FetchTokenRequest>';

        $session = new eBaySession($userToken, $this->devID, $this->appID, $this->certID, $this->serverUrl, $this->compatabilityLevel, $this->siteID, $verb);
        $responseXml = $session->sendHttpRequest($requestxml);

        //		$responseDoc = new DomDocument();
        //		$responseDoc->loadXML($responseXml);
        //		$errors = $responseDoc->getElementsByTagName('Errors');

        $data = XML_unserialize($responseXml);

        $getdata = $data['FetchTokenResponse'];

        $this->insertEbayApiLog($verb, $requestxml, $getdata); //获得ebayaip日志

        $token = @$getdata['eBayAuthToken'];
        $expirtime = @$getdata['HardExpirationTime'];
        return array('token' => $token, 'expirtime' => $expirtime);
    }

    //ebay api返回日志，$data是ebay返回数据中key是（$verb+Response）的数组
    public function insertEbayApiLog($verb, $requestxml, $data = array()) {
        $params = array();
        $params['api_type'] = $verb;
        $params['post_data'] = $requestxml;
        //a($params['post_data']);
        $params['created'] = date("Y-m-d H:i:s");
        $status = $data['Ack'];
        if ($status == "Success") {//ebay返回数据成功
            $params['status'] = 1;
            $params['return_data'] = "Success";
        } else {
            $params['status'] = 0;
            $params['return_data'] = $data['Errors']['ErrorCode'] . $data['Errors']['LongMessage'];
        }
        $sql = "INSERT INTO ebay_api_logs (api_type,post_data,return_data,status,created) VALUES('{$params['api_type']}','{$params['post_data']}','{$params['return_data']}','{$params['status']}','{$params['created']}')";
        //echo $sql;
        //return;
        $ret = db()->createCommand($sql)->execute();
        //		if($ret > 0) echo 'insert success';
        //		else echo 'insert fail';
    }

    //得到指定站点运送选项中运输方式
    public function geteBayDetialShipping($siteID = 0) {
        $requestxml = '<?xml version="1.0" encoding="utf-8"?>
					   <GeteBayDetailsRequest xmlns="urn:ebay:apis:eBLBaseComponents"> 
                       <RequesterCredentials> 
                       <eBayAuthToken>' . $this->token . '</eBayAuthToken> 
                       </RequesterCredentials> 
                       <DetailName>ShippingLocationDetails</DetailName>
                       <DetailName>ShippingCarrierDetails</DetailName> 
                       <DetailName>ShippingServiceDetails</DetailName>
                       </GeteBayDetailsRequest>';
        $verb = 'GeteBayDetails';
        $session = new eBaySession('', $this->devID, $this->appID, $this->certID, $this->serverUrl, $this->compatabilityLevel, $siteID, $verb);
        $responseXml = $session->sendHttpRequest($requestxml);
        $data = XML_unserialize($responseXml);
        return $data['GeteBayDetailsResponse'];
    }

    //得到别的卖家listing，时间段最大120天

    public function getSellerList($usrid, $startTimeFrom, $startTimeTo) {
        $verb = 'GetSellerList';
        $itemNumSum = 0;
        $pagesNumSum = 0;
        $pagesNum = 1;
        $seller_site = '';
        $dataItem = array();
        while (1) {
            $requestxml = '<?xml version="1.0" encoding="utf-8"?>
						  <GetSellerListRequest xmlns="urn:ebay:apis:eBLBaseComponents">
 						  <RequesterCredentials>
    					  <eBayAuthToken>' . $this->token . '</eBayAuthToken>
  						  </RequesterCredentials>
  						  <StartTimeFrom>' . $startTimeFrom . '</StartTimeFrom> 
  						  <StartTimeTo>' . $startTimeTo . '</StartTimeTo> 
    					  <UserID>' . $usrid . '</UserID>
    					   <Pagination> PaginationType
						    <EntriesPerPage>200</EntriesPerPage>
						    <PageNumber>' . $pagesNum . '</PageNumber>
						  </Pagination>
						  <!--CategoryID>6000</CategoryID-->
						  <OutputSelector>ItemArray.Item.ItemID</OutputSelector>
						  <OutputSelector>ItemArray.Item.Title</OutputSelector>
						  <OutputSelector>ItemArray.Item.ShippingDetails.ShippingServiceOptions.ShippingServiceCost</OutputSelector>
						  <OutputSelector>ItemArray.Item.PrimaryCategory.CategoryID</OutputSelector>
						  <OutputSelector>ItemArray.Item.PrimaryCategory.CategoryName</OutputSelector>
						  <OutputSelector>ItemArray.Item.ListingDetails.StartTime</OutputSelector>
						  <OutputSelector>ItemArray.Item.ListingDetails.EndTime</OutputSelector>						 
						  <OutputSelector>ItemArray.Item.Currency</OutputSelector>
						  <OutputSelector>HasMoreItems</OutputSelector>
						  <OutputSelector>ItemArray.Item.Site</OutputSelector>
                          <OutputSelector>ItemArray.Item.SellingStatus.QuantitySold</OutputSelector>
						  <OutputSelector>ItemArray.Item.SellingStatus.CurrentPrice</OutputSelector>
                          <OutputSelector>ItemArray.Item.Country</OutputSelector>
                          <OutputSelector>ReturnedItemCountActual</OutputSelector>
                          <OutputSelector>PaginationResult.TotalNumberOfEntries</OutputSelector>
                          <OutputSelector>PaginationResult.TotalNumberOfPages</OutputSelector>
                          <OutputSelector>Seller.Site</OutputSelector>
						   <OutputSelector>ItemArray.Item.SellingStatus.ListingStatus</OutputSelector>
						   <OutputSelector>ItemArray.Item.PictureDetails.PictureURL</OutputSelector>
                          <DetailLevel>ReturnAll</DetailLevel>
						  </GetSellerListRequest>';
            $session = new eBaySession('', $this->devID, $this->appID, $this->certID, $this->serverUrl, $this->compatabilityLevel, $this->siteID, $verb);
            $responseXml = $session->sendHttpRequest($requestxml);
            $data = XML_unserialize($responseXml);
            $getdata = @$data['GetSellerListResponse'];
            if ($pagesNum == 1) {
                $pagesNumSum = @$getdata['PaginationResult']['TotalNumberOfPages'];
                $itemNumSum = @$getdata['PaginationResult']['TotalNumberOfEntries'];
            }
            if ($pagesNumSum == 0)
                break;
            if (!isset($getdata['ItemArray']['Item']))
                break;
            if (isset($getdata['ReturnedItemCountActual']) && $getdata['ReturnedItemCountActual'] == 1)
                $getdata['ItemArray']['Item'] = array($getdata['ItemArray']['Item']);
            //			if($seller_site == ''){
            //				$seller_site = isset($getdata['Seller']['Site'])?$getdata['Seller']['Site']:"";
            //			}
            $dataItem = array_merge($dataItem, $getdata['ItemArray']['Item']);
            $pagesNum++;
            if ($pagesNum > $pagesNumSum || (isset($getdata['HasMoreItems']) && $getdata['HasMoreItems'] === false))
                break;
            //$this->insertEbayApiLog($verb,$requestxml,$getdata);//获得ebayaip日志
        }
        $data = array("num" => $itemNumSum, 'data' => $dataItem);
        return $data;
    }

    //得到item的详细信息
    public function getItemDetail($itemid) {
        $verb = 'GetItem';
        $requestxml = '<?xml version="1.0" encoding="utf-8"?>
					  	   <GetItemRequest xmlns="urn:ebay:apis:eBLBaseComponents">
                      	   <RequesterCredentials>
                      	   <eBayAuthToken>' . $this->token . '</eBayAuthToken> 
                      	   </RequesterCredentials>
  					  	   <ItemID>' . $itemid . '</ItemID>
  					  	 
                      	   </GetItemRequest>';
        $session = new eBaySession('', $this->devID, $this->appID, $this->certID, $this->serverUrl, $this->compatabilityLevel, $this->siteID, $verb);
        $responseXml = $session->sendHttpRequest($requestxml);

        $data = XML_unserialize($responseXml);
        $getdata = @$data['GetItemResponse'];
        if (!isset($getdata['Item']))
            return isset($getdata['Item']) ? $getdata['Item'] : array();
    }

    public function getPaypalAccount($token, $itemid) {
        $verb = 'GetItem';
        $requestxml = '<?xml version="1.0" encoding="utf-8"?>
		<GetItemRequest xmlns="urn:ebay:apis:eBLBaseComponents">
		  <RequesterCredentials>
		    <eBayAuthToken>' . $token . '</eBayAuthToken>
		  </RequesterCredentials>
		  		<ItemID>' . $itemid . '</ItemID>
		  		<OutputSelector>Item.PayPalEmailAddress</OutputSelector>
		</GetItemRequest>';
        $session = new eBaySession('', $this->devID, $this->appID, $this->certID, $this->serverUrl, $this->compatabilityLevel, $this->siteID, $verb);
        $responseXml = $session->sendHttpRequest($requestxml);
        $data = XML_unserialize($responseXml);
        $data = $data['GetItemResponse'];
        return isset($data['Item']['PayPalEmailAddress']) ? array(true, $data['Item']['PayPalEmailAddress']) : array(false, $data['Errors']['ShortMessage']);
    }

    //得到item的交易详细信息，时间段最大只能30天，最多只能获得最近3个月的交易记录
    public function getItemTransactions($itemid, $timeFrom, $timeTo) {
        $verb = 'GetItemTransactions';
        $requestxml = '<?xml version="1.0" encoding="utf-8"?>
                                <GetItemTransactionsRequest xmlns="urn:ebay:apis:eBLBaseComponents">
                                <ItemID>' . $itemid . '</ItemID>
                                <RequesterCredentials>
                                <eBayAuthToken>' . $this->token . '</eBayAuthToken>
                                </RequesterCredentials>
                                <ModTimeFrom>' . $timeFrom . '</ModTimeFrom>
                                <ModTimeTo>' . $timeTo . '</ModTimeTo>
                                </GetItemTransactionsRequest>';
        $session = new eBaySession('', $this->devID, $this->appID, $this->certID, $this->serverUrl, $this->compatabilityLevel, $this->siteID, $verb);
        $responseXml = $session->sendHttpRequest($requestxml);
        $data = XML_unserialize($responseXml);
        $num = isset($data['GetItemTransactionsResponse']['ReturnedTransactionCountActual']) ? $data['GetItemTransactionsResponse']['ReturnedTransactionCountActual'] : 0;
        $transaction = isset($data['GetItemTransactionsResponse']['TransactionArray']['Transaction']) ? $data['GetItemTransactionsResponse']['TransactionArray']['Transaction'] : array();
        if ($num > 1) {
            foreach ((array) $transaction as $v) {
                $tmp[] = array(
                    'CreatedDate' => $v['CreatedDate'],
                    'QuantityPurchased' => $v['QuantityPurchased'],
                    'TransactionPrice' => $v['TransactionPrice'],
                );
            }
        } elseif ($num == 1) {
            $tmp = array(
                'CreatedDate' => $transaction['CreatedDate'],
                'QuantityPurchased' => $transaction['QuantityPurchased'],
                'TransactionPrice' => $transaction['TransactionPrice'],
            );
        } else {
            $tmp = array();
        }
        unset($v, $transaction);
        return array('num' => $num, 'Transaction' => $tmp);
    }

    //得到ebay 分类
    //$CategorySiteID=0:除了ebay motor之外的所有分类，$CategorySiteID=100：得到ebay motor分类
    public function GetCategories($CategorySiteID) {
        $verb = 'GetCategories';
        $requestxml = '<?xml version="1.0" encoding="utf-8"?>
						  <GetCategoriesRequest xmlns="urn:ebay:apis:eBLBaseComponents">
						  <RequesterCredentials>
						    <eBayAuthToken>' . $this->token . '</eBayAuthToken>
						  </RequesterCredentials>
						  <CategorySiteID>' . $CategorySiteID . '</CategorySiteID>
						  <DetailLevel>ReturnAll</DetailLevel>
						  <!--LevelLimit>4</LevelLimit-->
						</GetCategoriesRequest>';
        $session = new eBaySession('', $this->devID, $this->appID, $this->certID, $this->serverUrl, $this->compatabilityLevel, $this->siteID, $verb);
        $responseXml = $session->sendHttpRequest($requestxml);

        $data = XML_unserialize($responseXml);
        return $data['GetCategoriesResponse']['CategoryArray']['Category'];
    }

    //得到的ebay 分类插入表中，只插入了除了ebay motor分类
    public function insertCategories() {
        $data = $this->GetCategories(100); //得到除了ebay motor之外的所有分类
        //		$data1 = $this->GetCategories(100);//得到ebay motor的所有分类
        //		$data += $data1;
        foreach ($data as $key => $value) {
            if (isset($value['Expired']) && $value['Expired'])
                continue;
            $LeafCategory = isset($value['LeafCategory']) ? 1 : 0;
            $CategoryName = str_replace("'", "''", $value['CategoryName']);
            $sql = "INSERT IGNORE INTO ebay_categorys(CategoryID,CategoryName,CategoryLevel,CategoryParentID,LeafCategory)
					VALUES('{$value['CategoryID']}','{$CategoryName}',{$value['CategoryLevel']},'{$value['CategoryParentID']}',$LeafCategory)";
            $ret = db()->createCommand($sql)->execute();
        }
    }

    //GetStores 获取自定义分类
    public function GetStoreApi($userid) {
        $verb = 'GetStore';
        $requestxml = '<?xml version="1.0" encoding="utf-8"?>
			<GetStoreRequest xmlns="urn:ebay:apis:eBLBaseComponents">
			<CategoryStructureOnly>true</CategoryStructureOnly>
			<UserID>' . $userid . '</UserID>
            <RequesterCredentials>
           <eBayAuthToken>' . $this->token . '</eBayAuthToken>
			<CategorySiteID>2</CategorySiteID>
           </RequesterCredentials>
			</GetStoreRequest>';
        $session = new eBaySession('', $this->devID, $this->appID, $this->certID, $this->serverUrl, $this->compatabilityLevel, $this->siteID, $verb);
        $responseXml = $session->sendHttpRequest($requestxml);
        $data = XML_unserialize($responseXml);
        return $data['GetStoreResponse']['Store']['CustomCategories']['CustomCategory'];
    }

    public function GetApi($itemid) {
        $verb = 'GetItem';
        $requestxml = '<?xml version="1.0" encoding="utf-8"?>
					  	   <GetItemRequest xmlns="urn:ebay:apis:eBLBaseComponents">
                      	   <RequesterCredentials>
                      	   <eBayAuthToken>' . $this->token . '</eBayAuthToken> 
                      	   </RequesterCredentials>
  					  	   <ItemID>' . $itemid . '</ItemID>
  					  	    <OutputSelector>Item.ListingDetails.StartTime</OutputSelector>
  					  	     <OutputSelector>Item.ListingDetails.EndTime</OutputSelector>
  					  	     <OutputSelector>Item.ListingDetails.EndTime</OutputSelector>
  					  	     <OutputSelector>Item.PrimaryCategory.CategoryID</OutputSelector>
                          <OutputSelector>Item.PrimaryCategory.CategoryName</OutputSelector>
                          <OutputSelector>Item.Currency</OutputSelector>
                          <DetailLevel>ReturnAll</DetailLevel>
                      	   </GetItemRequest>';
        $session = new eBaySession('', $this->devID, $this->appID, $this->certID, $this->serverUrl, $this->compatabilityLevel, $this->siteID, $verb);
        $responseXml = $session->sendHttpRequest($requestxml);

        $data = XML_unserialize($responseXml);
        return $data['GetItemResponse']['Item'];
    }

    public function GetTime() {
        $verb = 'GeteBayOfficialTime';
        $requestxml = '<?xml version="1.0" encoding="utf-8"?>
						<GeteBayOfficialTimeRequest xmlns="urn:ebay:apis:eBLBaseComponents">
						<RequesterCredentials>
                      	   <eBayAuthToken>' . $this->token . '</eBayAuthToken> 
                      	   </RequesterCredentials>
						</GeteBayOfficialTimeRequest>';
        $session = new eBaySession('', $this->devID, $this->appID, $this->certID, $this->serverUrl, $this->compatabilityLevel, $this->siteID, $verb);
        $responseXml = $session->sendHttpRequest($requestxml);

        $data = XML_unserialize($responseXml);
        return $data;
    }

    /*
      获取给站点下的分类
      $categorysiteid是站点id
      查看所有站点id调用GetSite方法
     */

    public function GetCategoriesApi($CategorySiteID) {
        $verb = 'GetCategories';
        $requestxml = '<?xml version="1.0" encoding="utf-8"?>
						  <GetCategoriesRequest xmlns="urn:ebay:apis:eBLBaseComponents">
						  <RequesterCredentials>
						    <eBayAuthToken>' . $this->token . '</eBayAuthToken>
						  </RequesterCredentials>
						  <CategorySiteID>' . $CategorySiteID . '</CategorySiteID>
						  <DetailLevel>ReturnAll</DetailLevel>
						  <!--LevelLimit>4</LevelLimit-->
						</GetCategoriesRequest>';
        $session = new eBaySession('', $this->devID, $this->appID, $this->certID, $this->serverUrl, $this->compatabilityLevel, $this->siteID, $verb);
        $responseXml = $session->sendHttpRequest($requestxml);
        $data = XML_unserialize($responseXml);
        return $data['GetCategoriesResponse']['CategoryArray']['Category'];
    }

    /*
      查看所有ebay站点的siteId
     */

    public function GetSite() {
        $verb = 'GeteBayDetails';
        $requestxml = '<?xml version="1.0" encoding="utf-8"?>
			<GeteBayDetailsRequest xmlns="urn:ebay:apis:eBLBaseComponents">
		     <RequesterCredentials>
             <eBayAuthToken>' . $this->token . '</eBayAuthToken> 
             </RequesterCredentials>
             <DetailName>ReturnPolicyDetails</DetailName>
			</GeteBayDetailsRequest>';
        $session = new eBaySession('', $this->devID, $this->appID, $this->certID, $this->serverUrl, $this->compatabilityLevel, $this->siteID, $verb);
        $responseXml = $session->sendHttpRequest($requestxml);
        $data = XML_unserialize($responseXml);
        return $data['GeteBayDetailsResponse']['SiteDetails'];
    }

    public function getSessionIDApi($token) {
        $verb = 'GetSessionID';
        $requestXmlBody = '<?xml version="1.0" encoding="utf-8"?>
						  <GetSessionIDRequest xmlns="urn:ebay:apis:eBLBaseComponents">
						  <RuName>' . $this->runame . '</RuName>
						  </GetSessionIDRequest>';
        $session = new eBaySession($token, $this->devID, $this->appID, $this->certID, $this->serverUrl, $this->compatabilityLevel, $this->siteID, $verb);
        $responseXml = $session->sendHttpRequest($requestXmlBody);
        $data = XML_unserialize($responseXml);
        return $data['GetSessionIDResponse']['SessionID'];
    }

    //二次交易接口
    public function addsecondchanceitem($price, $time, $itemid, $sellermessage) {
        $verb = 'AddSecondChanceItem';
        $requestxml = '<?xml version="1.0" encoding="utf-8"?>
					<AddSecondChanceItemRequest xmlns="urn:ebay:apis:eBLBaseComponents">
					 	<RequesterCredentials>
						 <eBayAuthToken>' . $this->token . '</eBayAuthToken>
					 	</RequesterCredentials>
					  <BuyItNowPrice>' . $price . ' </BuyItNowPrice>
					  <Duration>' . $time . '</Duration>
					  <ItemID>' . $itemid . '</ItemID>
					  <RecipientBidderUserID>' . $buyername . '</RecipientBidderUserID>
					  <SellerMessage>' . $sellermessage . '</SellerMessage>
					</AddSecondChanceItemRequest>';
        $session = new eBaySession('', $this->devID, $this->appID, $this->certID, $this->serverUrl, $this->compatabilityLevel, $this->siteID, $verb);
        $responseXml = $session->sendHttpRequest($requestxml);
        $data = XML_unserialize($responseXml);
        return $data;
    }

    /**
     *  商品下架接口
     */
    public function enditem($itemid) {
        $verb = 'EndItem';
        $requestxml = '<?xml version="1.0" encoding="utf-8"?>
					<EndItemRequest xmlns="urn:ebay:apis:eBLBaseComponents">
					  <RequesterCredentials>
					    <eBayAuthToken>' . $this->token . '</eBayAuthToken>
					  </RequesterCredentials>
					  <ItemID>' . $itemid . '</ItemID>
					  <EndingReason>NotAvailable</EndingReason>
					</EndItemRequest>';
        $session = new eBaySession('', $this->devID, $this->appID, $this->certID, $this->serverUrl, $this->compatabilityLevel, $this->siteID, $verb);
        $responseXml = $session->sendHttpRequest($requestxml);
        $data = XML_unserialize($responseXml);
        return $data['EndItemResponse'];
    }

    /*     * *****************************************GET EBAY ARGS*************************************** */

    public function GeteBayDetails() {
        $verb = 'GeteBayDetails';
        $requestxml = '<?xml version="1.0" encoding="utf-8"?>
			<GeteBayDetailsRequest xmlns="urn:ebay:apis:eBLBaseComponents">
		     <RequesterCredentials>
             <eBayAuthToken>' . $this->token . '</eBayAuthToken> 
             </RequesterCredentials>
			</GeteBayDetailsRequest>';
        $session = new eBaySession('', $this->devID, $this->appID, $this->certID, $this->serverUrl, $this->compatabilityLevel, $this->siteID, $verb);
        $responseXml = $session->sendHttpRequest($requestxml);
        return XML_unserialize($responseXml);
    }

    public function GeteBayPayment() {
        $verb = 'GeteBayDetails';
        $requestxml = '<?xml version="1.0" encoding="utf-8"?>
			<GeteBayDetailsRequest xmlns="urn:ebay:apis:eBLBaseComponents">
		     <RequesterCredentials>
             <eBayAuthToken>' . $this->token . '</eBayAuthToken> 
             </RequesterCredentials>
             <DetailName>PaymentOptionDetails</DetailName>
			</GeteBayDetailsRequest>';
        $session = new eBaySession('', $this->devID, $this->appID, $this->certID, $this->serverUrl, $this->compatabilityLevel, $this->siteID, $verb);
        $responseXml = $session->sendHttpRequest($requestxml);
        return XML_unserialize($responseXml);
    }

    //get countrys AND conuntrys short Api
    public function GeteBayExcludeShippingLocationDetails() {
        $verb = 'GeteBayDetails';
        $requestxml = '<?xml version="1.0" encoding="utf-8"?>
			<GeteBayDetailsRequest xmlns="urn:ebay:apis:eBLBaseComponents">
		     <RequesterCredentials>
             <eBayAuthToken>' . $this->token . '</eBayAuthToken> 
             <!--DetailName>CountryDetails</DetailName-->
             <!--DetailName>RegionOfOriginDetails</DetailName-->
             <DetailName>ExcludeShippingLocationDetails</DetailName>
             </RequesterCredentials>
			</GeteBayDetailsRequest>';
        $session = new eBaySession('', $this->devID, $this->appID, $this->certID, $this->serverUrl, $this->compatabilityLevel, $this->siteID, $verb);
        $responseXml = $session->sendHttpRequest($requestxml);
        return XML_unserialize($responseXml);
    }

    public function resetPrice($token, $xml) {
        $verb = 'ReviseInventoryStatus';
        $requestxml = '<?xml version="1.0" encoding="utf-8"?>
		<ReviseInventoryStatusRequest xmlns="urn:ebay:apis:eBLBaseComponents">
		  <RequesterCredentials>
		    <eBayAuthToken>' . $token . '</eBayAuthToken>
		  </RequesterCredentials>' . $xml . '
		</ReviseInventoryStatusRequest>';
        $session = new eBaySession('', $this->devID, $this->appID, $this->certID, $this->serverUrl, $this->compatabilityLevel, $this->siteID, $verb);
        $responseXml = $session->sendHttpRequest($requestxml);
        $data = XML_unserialize($responseXml);
        return $data['ReviseInventoryStatusResponse'];
    }

//////////////////////////////////////////////////////////////////////////////////////////////////	
    /**
     *  商品商家上架
     */
    public function addItem($params) {
        $verb = 'AddItem';
        $requestxml = '<?xml version="1.0" encoding="UTF-8"?>
			<AddItemRequest xmlns="urn:ebay:apis:eBLBaseComponents">
				<RequesterCredentials>
	            <eBayAuthToken>' . $this->token . '</eBayAuthToken> 
	            </RequesterCredentials>
				  <Item>
				    <CategoryMappingAllowed>true</CategoryMappingAllowed>
				    <SKU>' . $params['ebay_sku'] . '</SKU>
				    <Quantity>' . $params['listing_qty'] . '</Quantity>';
        if ($params['site_id'] == 77 || $params['site_id'] == 71 || $params['site_id'] == 101 || $params['site_id'] == 186) {
            $requestxml.='<Title>' . $params['listing_title1'] . '</Title>';
        } elseif (strstr($params['listing_title1'], "&")) {
            $requestxml.='<Title>' . htmlentities($params['listing_title1']) . '</Title>';
        } else {
            $requestxml.='<Title>' . $params['listing_title1'] . '</Title>';
        }
        //$requestxml.='<Title>'.htmlentities($params['listing_title1']).'</Title>';
        $requestxml.='<Site>' . $params['site_country'] . '</Site>
				    <Country>' . $params['site_ebay_tag'] . '</Country>
				    <Currency>' . $params['site_currency'] . '</Currency>
				    <description>discription</description>
				    <PictureDetails>' . $params['cover_picture'] . '</PictureDetails>
				    <DispatchTimeMax>1</DispatchTimeMax>
				    <ListingDuration>' . $params['listing_days'] . '</ListingDuration>';
        //if($params['location_name']=='DE' || $params['location_name']=='IT' || $params['location_name']=='FR'||$params['location_name']=='ES')$requestxml.='<Country>'.$params['location_name'].'</Country>';
        if ($params['location_name'] == 'DE' || $params['location_name'] == 'IT' || $params['location_name'] == 'FR' || $params['location_name'] == 'ES')
            $requestxml.='<Country>DE</Country>';
        if (!empty($params['location_code']))
            $requestxml.='<PostalCode>' . $params['location_code'] . '</PostalCode>';
        if (!empty($params['location_item']))
            $requestxml.='<Location>' . $params['location_item'] . '</Location>';
        $requestxml.='
						    <ListingType>' . $params['listing_type'] . '</ListingType>
						    <StartPrice>' . $params['listing_price'] . '</StartPrice>
						    <!--DispatchTimeMax>2</DispatchTimeMax-->
							<ConditionID>1000</ConditionID>
					<!--ConditionDescription>New</ConditionDescription-->';
        // 启用无货在线
        if ($params['non_line'])
            $requestxml.='
					<OutOfStockControl>true</OutOfStockControl>';
        //如果为拍卖
        if ($params['listing_type'] == 'Chinese')
            $requestxml.='
					<ReservePrice currencyID="' . $params['site_currency'] . '">' . $params['hode_price'] . '</ReservePrice>
					<BuyItNowPrice currencyID="' . $params['site_currency'] . '">' . $params['fix_price'] . '</BuyItNowPrice>';
        //payment
        /* 		if($params['payment']){
          foreach ($params['payment'] as $v) {
          if($v['paypaldetail_method']=='PayPal'){
          $requestxml.='<PaymentMethods>'.$v['paypaldetail_method'].'</PaymentMethods>';
          $requestxml.='<PayPalEmailAddress>'.$params['paypal_account'].'</PayPalEmailAddress>';
          }
          }
          } */
        $requestxml.='<PaymentMethods>PayPal</PaymentMethods>';
        $requestxml.='<PayPalEmailAddress>' . $params['paypal_account'] . '</PayPalEmailAddress>';

        //商店 一级分类
        if ($params['shop_category1'] || $params['shop_category2'])
            $requestxml.='<Storefront>';

        if ($params['shop_category1'])
            $requestxml.='<StoreCategoryID>' . $params['shop_category1_id'] . '</StoreCategoryID><StoreCategoryName>' . $params['shop_category1_name'] . '</StoreCategoryName>';

        //商店  二级分类
        if ($params['shop_category2'])
            $requestxml.=$requestxml.='<StoreCategory2ID>' . $params['shop_category1_id'] . '</StoreCategory2ID><StoreCategory2Name>' . $params['shop_category1_name'] . '</StoreCategory2Name>';
        if ($params['shop_category1'] || $params['shop_category2'])
            $requestxml.='</Storefront>';

        //ebay	一级分类
        $requestxml.='
				    <PrimaryCategory>
						<CategoryID>' . $params['listing_category1'] . '</CategoryID>
				    </PrimaryCategory>';
        //ebay 二级分类
        if ($params['listing_category2'] != 0)
            $requestxml.='
				    <SecondaryCategory>
						<CategoryID>' . $params['listing_category2'] . '</CategoryID>
				    </SecondaryCategory>';
        //二级标题
        if (trim($params['listing_title2']))
            $requestxml.='<SubTitle>' . $params['subtitle'] . '</SubTitle>';

        //分类属性
        if (count($params['item_specifics'])) {
            $requestxml.='<ItemSpecifics>';
            foreach ($params['item_specifics'] as $attr => $attrv) {
                $attr = htmlspecialchars($attr);
                $attrv = htmlspecialchars($attrv);
                $requestxml.="<NameValueList><Name>{$attr}</Name><Value>{$attrv}</Value></NameValueList>";
            }
            $requestxml.='</ItemSpecifics>';
        }

        //兼容属性接口
        if ($params['site_id'] == 100) {
            $requestxml .= '<ItemCompatibilityList>';
            foreach ($params['compatibilitiy_specifics'] as $compatibilitiyValue) {
                $requestxml.='<Compatibility>';
                foreach ($compatibilitiyValue as $_N => $_V) {
                    $_N = htmlspecialchars($_N);
                    $_V = htmlspecialchars($_V);
                    $requestxml.="<NameValueList><Name>{$_N}</Name><Value>{$_V}</Value></NameValueList>";
                }
                $requestxml.='</Compatibility>';
            }
            $requestxml.='</ItemCompatibilityList>';
  }
        //UPC
        $requestxml.='<ProductListingDetails>';
        if ($params['site_id'] == 3) {
            $requestxml.='<EAN>Does not apply</EAN>';
        } elseif ($params['site_id'] == 101 || $params['site_id'] == 186 || $params['site_id'] == 71) {
            $requestxml.='<EAN>Non applicable</EAN>';
        } elseif ($params['site_id'] == 77) {
            $requestxml.='<EAN>Nicht zutreffend</EAN>';
        } elseif ($params['site_id'] == 15) {
            //if($params['listing_type']!='Chinese')
            //{
            //$requestxml.='<EAN>Non applicable</EAN>';
            // }
            // else
            // {
            $requestxml.='<EAN>Non applicable</EAN>';
            $requestxml.='<UPC>Does not apply</UPC>';
            // }
        } else {
            $requestxml.='<UPC>Does not apply</UPC>';
        }
        $requestxml.='</ProductListingDetails>';
        //退货政策
        $rdays = ($params['returns']['returns_within_option'] == 'Months_1') ? 'Months_1' : 'Days_' . $params['returns']['returns_within_option'];
        if (isset($params['returns']))
            $requestxml.='
				    <ReturnPolicy>
				      <ReturnsAcceptedOption>' . $params['returns']['accepted_option'] . '</ReturnsAcceptedOption>';
        if ($params['returns']['refund_option'])
            $requestxml.='<RefundOption>' . $params['returns']['refund_option'] . '</RefundOption>';
        $requestxml.='<ReturnsWithinOption>' . $rdays . '</ReturnsWithinOption>
				      <Description>' . $params['returns']['discription'] . '</Description>
					  <RestockingFeeValueOption>' . $params['returns']['percent'] . '</RestockingFeeValueOption>
				      <ShippingCostPaidByOption>' . $params['returns']['costpai_option'] . '</ShippingCostPaidByOption>
				    </ReturnPolicy>
				    <ShippingDetails>';

        //国内运输选项
        foreach ((array) $params['shipping']['location'] as $k => $v) {
            //<ShippingType>Flat</ShippingType>
            $requestxml.='
			    
			    <ShippingServiceOptions>
			        <ShippingServicePriority>' . ($k + 1) . '</ShippingServicePriority>
			        <ShippingService>' . $v['location_method'] . '</ShippingService>';
            //免运费判断
            //if($v['location_freeshipping'])$requestxml.='<FreeShipping>true</FreeShipping>';
            //运费
            //判断是否有国内运费
            if ($params['internal_fare'] > 0) {
                $requestxml.='<ShippingServiceCost>' . $params['internal_fare'] . '</ShippingServiceCost>';
                //额外收费
                $requestxml.='<ShippingServiceAdditionalCost>' . $params['internal_fare'] . '</ShippingServiceAdditionalCost>';
            } else {
                //免运费判断
                if ($v['location_freeshipping'])
                    $requestxml.='<FreeShipping>true</FreeShipping>';
                $requestxml.='<ShippingServiceCost>' . $v['location_cost'] . '</ShippingServiceCost>';
                //额外收费
                $requestxml.='<ShippingServiceAdditionalCost>' . $v['location_addcost'] . '</ShippingServiceAdditionalCost>';
            }
            //如果是美国则出现高区收费
            if ($v['location_extra_cost'])
                $requestxml.='<ShippingSurcharge>' . $v['location_extra_cost'] . '</ShippingSurcharge>';
            $requestxml.='</ShippingServiceOptions>';
        }
        if ($params['cxy'] != 1) {
            
            $requestxml.='<ShippingType>Flat</ShippingType>';
            //国际运费选项
            foreach ((array) $params['shipping']['international'] as $k => $v) {
                $requestxml.='<InternationalShippingServiceOption>
				        <ShippingServicePriority>' . ($k + 1) . '</ShippingServicePriority>
				        <ShippingService>' . $v['international_method'] . '</ShippingService>
				        <ShippingServiceCost>' . $v['international_cost'] . '</ShippingServiceCost>
				        <ShippingServiceAdditionalCost>' . $v['international_addcost'] . '</ShippingServiceAdditionalCost>';
                foreach (explode(',', $v['international_allow_countrys']) as $vs)
                    $requestxml.='<ShipToLocation>' . $vs . '</ShipToLocation>';
                $requestxml.='</InternationalShippingServiceOption>';
            }
        }
        //运送排除
        if ($params['shipping']['shipping_template_exclude']) {
            foreach (explode(',', $params['shipping']['shipping_template_exclude']) as $v) {
                $requestxml.='<ExcludeShipToLocation>' . $v . '</ExcludeShipToLocation>';
            }
        }
        $requestxml.='</ShippingDetails>';

        //开启议价功能
        if ($params['accept_bargaining'])
            $requestxml.='<BestOfferDetails><BestOfferEnabled>true</BestOfferEnabled></BestOfferDetails>';
        //议价金额选项
        if ($params['accept_bargaining'] AND ( $params['accept_max'] OR $params['refuse_min']))
            $requestxml.='<ListingDetails>';
        if ($params['accept_max'])
            $requestxml.='<BestOfferAutoAcceptPrice currencyID="' . $params['site_currency'] . '">' . $params['accept_max_value'] . '</BestOfferAutoAcceptPrice>';
        if ($params['refuse_min'])
            $requestxml.='<MinimumBestOfferPrice currencyID="' . $params['site_currency'] . '">' . $params['refuse_min_value'] . '</MinimumBestOfferPrice>';
        if ($params['accept_bargaining'] AND ( $params['accept_max'] OR $params['refuse_min']))
            $requestxml.='</ListingDetails>';
        $requestxml.='</Item></AddItemRequest>';
        
//        file_put_contents(dirname(__DIR__).DIRECTORY_SEPARATOR.'logs'.DIRECTORY_SEPARATOR.'local3.txt',$requestxml);
//        exit('test');
        //file_put_contents('E:/Additem/'.$params['ebay_sku'].$params['listing_id'].'.txt',$requestxml);
        $session = new eBaySession('', $this->devID, $this->appID, $this->certID, $this->serverUrl, $this->compatabilityLevel, $this->siteID, $verb);
        $responseXml = $session->sendHttpRequest($requestxml);
        $data = XML_unserialize($responseXml);
        unset($data['AddItemResponse attr']);
        return $data;
    }

    /*     * *****************************************************物品属性************************************************** */

    public function GetCategorySpecifics($site) {
        $verb = 'GetCategorySpecifics';
        $requestxml = '<?xml version="1.0" encoding="utf-8"?>
					<GetCategorySpecificsRequest xmlns="urn:ebay:apis:eBLBaseComponents">
					  <RequesterCredentials>
					    <eBayAuthToken>' . $this->token . '</eBayAuthToken>
					  </RequesterCredentials>
					  <CategorySpecific>
					    <CategoryID>1</CategoryID>
					    <ItemSpecifics>
							<NameValueList>
								<Name>kids</Name>
							</NameValueList>
					    </ItemSpecifics>
					  </CategorySpecific>
					  <ExcludeRelationships>true</ExcludeRelationships>
					  <IncludeConfidence>true</IncludeConfidence>
					  <CategorySpecificsFileInfo>true</CategorySpecificsFileInfo>
					</GetCategorySpecificsRequest>';
        $session = new eBaySession('', $this->devID, $this->appID, $this->certID, $this->serverUrl, $this->compatabilityLevel, $site, $verb);
        $responseXml = $session->sendHttpRequest($requestxml);
        $data = XML_unserialize($responseXml);
        return $data['GetCategorySpecificsResponse'];
    }

    /**
     *  多属性商品商家上架 */
    public function addFixedPriceItem($params) {
        $verb = 'AddFixedPriceItem';
        $requestxml = '<?xml version="1.0" encoding="UTF-8"?>
		    <AddFixedPriceItemRequest xmlns="urn:ebay:apis:eBLBaseComponents">
				<RequesterCredentials>
	            <eBayAuthToken>' . $this->token . '</eBayAuthToken> 
	            </RequesterCredentials>
				  <Item>
				    <CategoryMappingAllowed>true</CategoryMappingAllowed>
				    <SKU>' . $params['ebay_sku'] . '</SKU>';
        //<Quantity>'.$params['listing_qty'].'</Quantity>';
        $requestxml.='<Title>' . $params['listing_title1'] . '</Title>';
        $requestxml.='<Site>' . $params['site_country'] . '</Site>
				    <Country>' . $params['site_ebay_tag'] . '</Country>
				    <Currency>' . $params['site_currency'] . '</Currency>
				    <description>discription</description>
				    <PictureDetails>' . $params['cover_picture'] . '</PictureDetails>
				    <DispatchTimeMax>1</DispatchTimeMax>
				    <ListingDuration>' . $params['listing_days'] . '</ListingDuration>';
        //if($params['location_name']=='DE' || $params['location_name']=='IT' || $params['location_name']=='FR'||$params['location_name']=='ES')$requestxml.='<Country>'.$params['location_name'].'</Country>';
        if ($params['location_name'] == 'DE' || $params['location_name'] == 'IT' || $params['location_name'] == 'FR' || $params['location_name'] == 'ES')
            $requestxml.='<Country>DE</Country>';
        if (!empty($params['location_code']))
            $requestxml.='<PostalCode>' . $params['location_code'] . '</PostalCode>';
        if (!empty($params['location_item']))
            $requestxml.='<Location>' . $params['location_item'] . '</Location>';
        $requestxml.='
						    <ListingType>' . $params['listing_type'] . '</ListingType>						  
						    <!--DispatchTimeMax>2</DispatchTimeMax-->
							<ConditionID>1000</ConditionID>
					<!--ConditionDescription>New</ConditionDescription-->';
        // 启用无货在线
        if ($params['non_line'])
            $requestxml.='
					<OutOfStockControl>true</OutOfStockControl>';
        //如果为拍卖
        /* if($params['listing_type']=='Chinese')$requestxml.='
          <ReservePrice currencyID="'.$params['site_currency'].'">'.$params['hode_price'].'</ReservePrice>
          <BuyItNowPrice currencyID="'.$params['site_currency'].'">'.$params['fix_price'].'</BuyItNowPrice>'; */
        //payment
        /* 		if($params['payment']){
          foreach ($params['payment'] as $v) {
          if($v['paypaldetail_method']=='PayPal'){
          $requestxml.='<PaymentMethods>'.$v['paypaldetail_method'].'</PaymentMethods>';
          $requestxml.='<PayPalEmailAddress>'.$params['paypal_account'].'</PayPalEmailAddress>';
          }
          }
          } */
        $requestxml.='<PaymentMethods>PayPal</PaymentMethods>';
        $requestxml.='<PayPalEmailAddress>' . $params['paypal_account'] . '</PayPalEmailAddress>';

        //商店 一级分类
        if ($params['shop_category1'] || $params['shop_category2'])
            $requestxml.='<Storefront>';

        if ($params['shop_category1'])
            $requestxml.='<StoreCategoryID>' . $params['shop_category1_id'] . '</StoreCategoryID><StoreCategoryName>' . $params['shop_category1_name'] . '</StoreCategoryName>';

        //商店  二级分类
        if ($params['shop_category2'])
            $requestxml.=$requestxml.='<StoreCategory2ID>' . $params['shop_category1_id'] . '</StoreCategory2ID><StoreCategory2Name>' . $params['shop_category1_name'] . '</StoreCategory2Name>';
        if ($params['shop_category1'] || $params['shop_category2'])
            $requestxml.='</Storefront>';

        //ebay	一级分类
        $requestxml.='
				    <PrimaryCategory>
						<CategoryID>' . $params['listing_category1'] . '</CategoryID>
				    </PrimaryCategory>';
        //ebay 二级分类
        if ($params['listing_category2'] != 0)
            $requestxml.='
				    <SecondaryCategory>
						<CategoryID>' . $params['listing_category2'] . '</CategoryID>
				    </SecondaryCategory>';
        //二级标题
        if (trim($params['listing_title2']))
            $requestxml.='<SubTitle>' . $params['subtitle'] . '</SubTitle>';

        //分类属性
        if (count($params['item_specifics'])) {
            $requestxml.='<ItemSpecifics>';
            foreach ($params['item_specifics'] as $attr => $attrv) {
                $attr = htmlspecialchars($attr);
                $attrv = htmlspecialchars($attrv);
                $requestxml.="<NameValueList><Name>{$attr}</Name><Value>{$attrv}</Value></NameValueList>";
            }
            $requestxml.='</ItemSpecifics>';
        }

        //兼容属性接口
        /* if($params['site_id']==100){
          $requestxml .= '<ItemCompatibilityList>';
          foreach ($params['compatibilitiy_specifics'] as $compatibilitiyValue) {
          $requestxml.='<Compatibility>';
          foreach ($compatibilitiyValue as $_N => $_V) {
          $_N = htmlspecialchars($_N);
          $_V = htmlspecialchars($_V);
          $requestxml.="<NameValueList><Name>{$_N}</Name><Value>{$_V}</Value></NameValueList>";
          }
          $requestxml.='</Compatibility>';
          }
          $requestxml.='</ItemCompatibilityList>';
          } */
        //UPC
        $requestxml.='<ProductListingDetails>';
        if ($params['site_id'] == 3) {
            $requestxml.='<EAN>Does not apply</EAN>';
        } elseif ($params['site_id'] == 101 || $params['site_id'] == 186) {
            $requestxml.='<EAN>No aplicable</EAN>';
        } elseif ($params['site_id'] == 71) {
            $requestxml.='<EAN>Non applicable</EAN>';
        } elseif ($params['site_id'] == 77) {
            $requestxml.='<EAN>Nicht zutreffend</EAN>';
        } else {
            $requestxml.='<UPC>Does not apply</UPC>';
        }
        $requestxml.='</ProductListingDetails>';
        //退货政策
        $rdays = ($params['returns']['returns_within_option'] == 'Months_1') ? 'Months_1' : 'Days_' . $params['returns']['returns_within_option'];
        if (isset($params['returns']))
            $requestxml.='
				    <ReturnPolicy>
				      <ReturnsAcceptedOption>' . $params['returns']['accepted_option'] . '</ReturnsAcceptedOption>';
        if ($params['returns']['refund_option'])
            $requestxml.='<RefundOption>' . $params['returns']['refund_option'] . '</RefundOption>';
        $requestxml.='<ReturnsWithinOption>' . $rdays . '</ReturnsWithinOption>
				      <Description>' . $params['returns']['discription'] . '</Description>
				      <ShippingCostPaidByOption>' . $params['returns']['costpai_option'] . '</ShippingCostPaidByOption>
				    </ReturnPolicy>
				    <ShippingDetails>';

        //国内运输选项
        foreach ((array) $params['shipping']['location'] as $k => $v) {
            $requestxml.='
			    <ShippingType>Flat</ShippingType>
			    <ShippingServiceOptions>
			        <ShippingServicePriority>' . ($k + 1) . '</ShippingServicePriority>
			        <ShippingService>' . $v['location_method'] . '</ShippingService>';
            //免运费判断
            if ($v['location_freeshipping'])
                $requestxml.='<FreeShipping>true</FreeShipping>';
            //运费		
            $requestxml.='<ShippingServiceCost>' . $v['location_cost'] . '</ShippingServiceCost>';
            //额外收费
            $requestxml.='<ShippingServiceAdditionalCost>' . $v['location_addcost'] . '</ShippingServiceAdditionalCost>';

            //如果是美国则出现高区收费
            if ($v['location_extra_cost'])
                $requestxml.='<ShippingSurcharge>' . $v['location_extra_cost'] . '</ShippingSurcharge>';
            $requestxml.='</ShippingServiceOptions>';
        }
        if ($params['cxy'] != 1) {
            //国际运费选项
            foreach ((array) $params['shipping']['international'] as $k => $v) {
                $requestxml.='<InternationalShippingServiceOption>
				        <ShippingServicePriority>' . ($k + 1) . '</ShippingServicePriority>
				        <ShippingService>' . $v['international_method'] . '</ShippingService>
				        <ShippingServiceCost>' . $v['international_cost'] . '</ShippingServiceCost>
				        <ShippingServiceAdditionalCost>' . $v['international_addcost'] . '</ShippingServiceAdditionalCost>';
                foreach (explode(',', $v['international_allow_countrys']) as $vs)
                    $requestxml.='<ShipToLocation>' . $vs . '</ShipToLocation>';
                $requestxml.='</InternationalShippingServiceOption>';
            }
        }
        //运送排除
        if ($params['shipping']['shipping_template_exclude']) {
            foreach (explode(',', $params['shipping']['shipping_template_exclude']) as $v) {
                $requestxml.='<ExcludeShipToLocation>' . $v . '</ExcludeShipToLocation>';
            }
        }
        $requestxml.='</ShippingDetails>';

        //开启议价功能
        //	if($params['accept_bargaining'])$requestxml.='<BestOfferDetails><BestOfferEnabled>true</BestOfferEnabled></BestOfferDetails>';
        //议价金额选项
        //	if($params['accept_bargaining'] AND ($params['accept_max'] OR $params['refuse_min']))$requestxml.='<ListingDetails>';
        //	if($params['accept_max'])$requestxml.='<BestOfferAutoAcceptPrice currencyID="'.$params['site_currency'].'">'.$params['accept_max_value'].'</BestOfferAutoAcceptPrice>';
        //	if($params['refuse_min'])$requestxml.='<MinimumBestOfferPrice currencyID="'.$params['site_currency'].'">'.$params['refuse_min_value'].'</MinimumBestOfferPrice>';
        //	if($params['accept_bargaining'] AND ($params['accept_max'] OR $params['refuse_min']))$requestxml.='</ListingDetails>';   
        //多属性开始
        $requestxml.='<Variations>';

        foreach ((array) $params['attribute'] as $key => $vel) {
            $requestxml.='<Variation>';
            //多属性sku
            $requestxml.='<SKU>' . $vel['attribute_sku'] . '</SKU>';
            //多属性数量
            $requestxml.='<Quantity>' . $vel['listing_qty'] . '</Quantity>';
            //多属性价格
            $requestxml.='<StartPrice>' . $vel['listing_price'] . '</StartPrice>';
            $requestxml.=' <VariationProductListingDetails>';
            if ($params['site_id'] == 3 || $params['site_id'] == 71 || $params['site_id'] == 77 || $params['site_id'] == 101 || $params['site_id'] == 186) {
                $requestxml.='<EAN>Does not apply</EAN>';
            } else {
                $requestxml.='<UPC>Does not apply</UPC>';
            }
            $requestxml.='</VariationProductListingDetails>';
            $requestxml.='<VariationSpecifics>';
            foreach ((array) $vel['shux'] as $k => $v) {
                $requestxml.='<NameValueList>
                    <Name>' . $v['sx_name'] . '</Name>
                    <Value>' . $v['sx_value'] . '</Value>           
                  </NameValueList>';
            }
            $requestxml.='</VariationSpecifics>';
            $requestxml.='</Variation>';
        }
        //多属性封面图片
        /* $requestxml.='<Pictures>';     
          //多属性名称
          $requestxml.='<VariationSpecificName>'.$params['attribute'][0]['attribute_picture']['shu'].'</VariationSpecificName>';
          //多属性值
          foreach ((array)$params['attribute'] as $key1 => $vel1)
          {

          $requestxml.='<VariationSpecificPictureSet>';
          $requestxml.='<VariationSpecificValue>'.$vel1['attribute_picture']['vale'].'</VariationSpecificValue>';
          $requestxml.='<PictureDetails>'.implode(",",$vel1['attribute_picture']['pic']);
          foreach ((array)$vel1['attribute_picture']['pic'] as $key => $vel)
          {
          $requestxml.=PHP_EOL.'<PictureURL>'.$vel.'?'.mt_rand(100,99999).'</PictureURL>';
          }
          $requestxml.='</PictureDetails>';
          $requestxml.='</VariationSpecificPictureSet>';
          }
          $requestxml.='</Pictures>'; */
        //多属性封面图片
        $requestxml.='<Pictures>';
        //多属性名称      
        $requestxml.='<VariationSpecificName>' . $params['attribute'][0]['attribute_picture']['shu'] . '</VariationSpecificName>';
        //多属性值
        foreach ((array) $params['attribute'] as $key1 => $vel1) {

            $requestxml.='<VariationSpecificPictureSet>';
            $requestxml.='<VariationSpecificValue>' . $vel1['attribute_picture']['vale'] . '</VariationSpecificValue>';
            foreach ((array) $vel1['attribute_picture']['pic'] as $key => $vel) {
                $requestxml.='<PictureURL>' . $vel . '?' . mt_rand(100, 99999) . '</PictureURL>';
            }
            $requestxml.='</VariationSpecificPictureSet>';
        }
        $requestxml.='</Pictures>';
        $requestxml.='<VariationSpecificsSet>';
        foreach ((array) $params['attribute'] as $key => $vel) {

            /* foreach ((array)$vel['shux'] as $k => $v)
              {
              $requestxml.='<NameValueList>
              <Name>'.$v['sx_name'].'</Name>
              <Value>'.$v['sx_value'].'</Value>
              </NameValueList>';
              } */

            foreach ($vel['shux'] as $k => $v) {
                $spsx[$k][$v['sx_name']][] = $v['sx_value'];
            }
        }
        foreach ($spsx as $key => $val) {
            $requestxml.="<NameValueList>";
            foreach ($val as $k => $v) {
                $requestxml.="<Name>$k</Name>";
                foreach ($v as $kk => $vv) {
                    $requestxml.="<Value>$vv</Value>";
                }
            }

            $requestxml.="</NameValueList>";
        }
        $requestxml.='</VariationSpecificsSet>';
        $requestxml.='</Variations>';
        //多属性结束       	
        $requestxml.='</Item></AddFixedPriceItemRequest>';
        //file_put_contents('D:/Additem/'.$params['ebay_sku'].$params['listing_id'].'.txt',$requestxml);  
        $session = new eBaySession('', $this->devID, $this->appID, $this->certID, $this->serverUrl, $this->compatabilityLevel, $this->siteID, $verb);
        $responseXml = $session->sendHttpRequest($requestxml);
        $data = XML_unserialize($responseXml);
        unset($data['AddFixedPriceItemResponse attr']);
        return $data;
    }

    /**
     *  修改退货政策,
     */
    public function zhengce($params) {
        $verb = 'ReviseItem';
        $rdays = ($params['days_char'] == 'Months_1') ? 'Months_1' : 'Days_' . $params['days_char'];
        $requestxml = '<?xml version="1.0" encoding="UTF-8"?>
			<ReviseItemRequest xmlns="urn:ebay:apis:eBLBaseComponents">
				<RequesterCredentials>
	            <eBayAuthToken>' . $params['ebay_token'] . '</eBayAuthToken> 
	            </RequesterCredentials>
				  <Item>
				    <ItemID>' . $params['item_id'] . '</ItemID>';
        //退货政策
        $requestxml.='
				    <ReturnPolicy>
				      <ReturnsAcceptedOption>ReturnsAccepted</ReturnsAcceptedOption>';
        if ($params['method_char'] != 0) {
            $requestxml.='<RefundOption>' . $params['method_char'] . '</RefundOption>';
        }
        $requestxml.='<ReturnsWithinOption>' . $rdays . '</ReturnsWithinOption>                      
				      <Description >' . $params['return_details'] . '</Description>
                      <RestockingFeeValueOption>' . $params['percent'] . '</RestockingFeeValueOption> 
				      <ShippingCostPaidByOption>' . $params['costpai_option'] . '</ShippingCostPaidByOption>
				    </ReturnPolicy>';

        //议价选项	
        $requestxml.='</Item></ReviseItemRequest>';
        //file_put_contents('D:/Additem/999999.txt',$requestxml);
        $session = new eBaySession('', $this->devID, $this->appID, $this->certID, $this->serverUrl, $this->compatabilityLevel, $this->siteID, $verb);
        $responseXml = $session->sendHttpRequest($requestxml);
        $data = XML_unserialize($responseXml);
        return $data;
    }

    /**
     * 重新刊登  
     */
    public function relistItem($params) {
        $verb = 'RelistItem';
        $requestxml = '<?xml version="1.0" encoding="utf-8"?>
		<RelistItemRequest xmlns="urn:ebay:apis:eBLBaseComponents">
			<RequesterCredentials>
	        <eBayAuthToken>' . $params['ebay_token'] . '</eBayAuthToken> 
	        </RequesterCredentials>
		  <Item>
		    <ItemID>' . $params['item_id'] . '</ItemID>
		  </Item>
		</RelistItemRequest>';
        $session = new eBaySession('', $this->devID, $this->appID, $this->certID, $this->serverUrl, $this->compatabilityLevel, $params['site_id'], $verb);
        $responseXml = $session->sendHttpRequest($requestxml);
        $data = XML_unserialize($responseXml);
        return $data;
    }

    /**
     *  商品商家修改,
     */
    public function updateItem($params) {
        $verb = 'ReviseItem';
        $requestxml = '<?xml version="1.0" encoding="UTF-8"?>
			<ReviseItemRequest xmlns="urn:ebay:apis:eBLBaseComponents">
				<RequesterCredentials>
	            <eBayAuthToken>' . $this->token . '</eBayAuthToken> 
	            </RequesterCredentials>
				  <Item>
				    <ItemID>' . $params['item_id'] . '</ItemID>
				    <Quantity>' . $params['listing_qty'] . '</Quantity>
				    <Title>' . $params['listing_title1'] . '</Title>
				    <DispatchTimeMax>1</DispatchTimeMax>
				    <ListingDuration>' . $params['listing_days'] . '</ListingDuration>
				    <Location>' . $params['site_english'] . '</Location>
				    <StartPrice>' . $params['listing_price'] . '</StartPrice>';
        // 启用无货在线
        if ($params['non_line'])
            $requestxml.='
					<OutOfStockControl>true</OutOfStockControl>';
        //如果为拍卖
        if ($params['listing_type'] == 'Chinese')
            $requestxml.='
					<ReservePrice currencyID="' . $params['site_currency'] . '">' . $params['hode_price'] . '</ReservePrice>
					<BuyItNowPrice currencyID="' . $params['site_currency'] . '">' . $params['fix_price'] . '</BuyItNowPrice>';
        //payment
        if ($params['payment']) {
            foreach ($params['payment'] as $v) {
                if ($v['paypaldetail_method'] == 'PayPal') {
                    $requestxml.='<PaymentMethods>' . $v['paypaldetail_method'] . '</PaymentMethods>';
                    $requestxml.='<PayPalEmailAddress>' . $params['paypal_account'] . '</PayPalEmailAddress>';
                }
            }
        }
        //商店 一级分类
        if ($params['shop_category1'] || $params['shop_category2'])
            $requestxml.='<Storefront>';

        if ($params['shop_category1'])
            $requestxml.='<StoreCategoryID>' . $params['shop_category1'] . '</StoreCategoryID>';

        //商店  二级分类
        if ($params['shop_category2'])
            $requestxml.='StoreCategory2ID> long </StoreCategory2ID';
        if ($params['shop_category1'] || $params['shop_category2'])
            $requestxml.='</Storefront>';

        //ebay	一级分类
        $requestxml.='
				    <PrimaryCategory>
						<CategoryID>' . $params['listing_category1'] . '</CategoryID>
				    </PrimaryCategory>';
        //ebay 二级分类
        if ($params['listing_category2'] != 0)
            $requestxml.='
				    <SecondaryCategory>
						<CategoryID>' . $params['listing_category2'] . '</CategoryID>
				    </SecondaryCategory>';
        //二级标题
        if (trim($params['listing_title2']))
            $requestxml.=
                    '<SubTitle>' . $params['subtitle'] . '</SubTitle>';

        //兼容属性接口
        if ($params['site_id'] == 100) {
            $requestxml .= '<ItemCompatibilityList>';
            foreach ($params['compatibilitiy_specifics'] as $compatibilitiyValue) {
                $requestxml.='<Compatibility>';
                foreach ($compatibilitiyValue as $_N => $_V) {
                    $requestxml.="<NameValueList><Name>{$_N}</Name><Value>{$_V}</Value></NameValueList>";
                }
                $requestxml.='</Compatibility>';
            }
            $requestxml.='</ItemCompatibilityList>';
        }
        //退货政策
        if (isset($params['returns']))
            $requestxml.='
				    <ReturnPolicy>
				      <ReturnsAcceptedOption>' . $params['returns']['accepted_option'] . '</ReturnsAcceptedOption>
				      <RefundOption>' . $params['returns']['refund_option'] . '</RefundOption>
				      <ReturnsWithinOption>Days_' . $params['returns']['returns_within_option'] . '</ReturnsWithinOption>
				      <Description>' . $params['returns']['discription'] . '</Description>
				      <ShippingCostPaidByOption>' . $params['returns']['costpai_option'] . '</ShippingCostPaidByOption>
				    </ReturnPolicy>';
        //议价选项
        //开启议价功能
        if ($params['accept_bargaining'])
            $requestxml.='<BestOfferDetails><BestOfferEnabled>true</BestOfferEnabled></BestOfferDetails>';
        //议价金额选项
        if ($params['accept_bargaining'] AND ( $params['accept_max'] OR $params['refuse_min']))
            $requestxml.='<ListingDetails>';
        if ($params['accept_max'])
            $requestxml.='<BestOfferAutoAcceptPrice currencyID="' . $params['site_currency'] . '">' . $params['accept_max_value'] . '</BestOfferAutoAcceptPrice>';
        if ($params['refuse_min'])
            $requestxml.='<MinimumBestOfferPrice currencyID="' . $params['site_currency'] . '">' . $params['refuse_min_value'] . '</MinimumBestOfferPrice>';
        if ($params['accept_bargaining'] AND ( $params['accept_max'] OR $params['refuse_min']))
            $requestxml.='</ListingDetails>';
        $requestxml.='</Item></ReviseItemRequest>';
        $session = new eBaySession('', $this->devID, $this->appID, $this->certID, $this->serverUrl, $this->compatabilityLevel, $this->siteID, $verb);
        $responseXml = $session->sendHttpRequest($requestxml);
        $data = XML_unserialize($responseXml);
        return $data;
    }

    /*     * **********************************************MARKDOWN API START***************************************** */

    /**
     *  add 创建一个折扣活动
     */
    public function addMarkdownToEbay($data = array()) {
        $verb = 'SetPromotionalSale';
		$requestxml='';
        $requestxml.='<?xml version="1.0" encoding="utf-8"?>
					<SetPromotionalSaleRequest xmlns="urn:ebay:apis:eBLBaseComponents">
					  <RequesterCredentials>
					    <eBayAuthToken>' . $this->token . '</eBayAuthToken>
					  </RequesterCredentials>
					  <Action>Add</Action>
					  <PromotionalSaleDetails>
					    <PromotionalSaleName>' . $data['mark_down_name'] . '</PromotionalSaleName>
					    <PromotionalSaleStartTime>' . $data['begin_date'] . '</PromotionalSaleStartTime>
					    <PromotionalSaleEndTime>' . $data['end_date'] . '</PromotionalSaleEndTime>';
        if ($data['price_discount'])
            $requestxml.='<DiscountType>' . $data['mark_down_type'] . '</DiscountType>
					    <DiscountValue>' . $data['mark_down_value'] . '</DiscountValue>';

        $requestxml.='<PromotionalSaleType>' . $data['free_shipping'] . '</PromotionalSaleType>
		 			 </PromotionalSaleDetails>
					</SetPromotionalSaleRequest>';
        //a($requestxml);
       // file_put_contents('D:/zhekou.txt',$requestxml);
        $session = new eBaySession('', $this->devID, $this->appID, $this->certID, $this->serverUrl, $this->compatabilityLevel, $this->siteID, $verb);
        $responseXml = $session->sendHttpRequest($requestxml);
        $data = XML_unserialize($responseXml);
        return $data;
    }

    /**
     *  edit 修改一个折扣活动
     */
    public function editMarkdownToEbay($data = array()) {
        $verb = 'SetPromotionalSale';
		$requestxml='';
        $requestxml.='<?xml version="1.0" encoding="utf-8"?>
					<SetPromotionalSaleRequest xmlns="urn:ebay:apis:eBLBaseComponents">
					  <RequesterCredentials>
					    <eBayAuthToken>' . $this->token . '</eBayAuthToken>
					  </RequesterCredentials>
					  <Action>Update</Action>
					  <PromotionalSaleDetails>
					    <PromotionalSaleID>' . $data['ebay_mark_down_id'] . '</PromotionalSaleID>
					    <PromotionalSaleName>' . $data['mark_down_name'] . '</PromotionalSaleName>
					    <PromotionalSaleStartTime>' . $data['begin_date'] . '</PromotionalSaleStartTime>
					    <PromotionalSaleEndTime>' . $data['end_date'] . '</PromotionalSaleEndTime>';
        if ($data['price_discount'])
            $requestxml.='
						<DiscountType>' . $data['mark_down_type'] . '</DiscountType>
					    <DiscountValue>' . $data['mark_down_value'] . '</DiscountValue>';

        $requestxml.='
		  				<PromotionalSaleType>' . $data['free_shipping'] . '</PromotionalSaleType>
		 			 </PromotionalSaleDetails>
					</SetPromotionalSaleRequest>';
        //a($requestxml);
        $session = new eBaySession('', $this->devID, $this->appID, $this->certID, $this->serverUrl, $this->compatabilityLevel, $this->siteID, $verb);
        $responseXml = $session->sendHttpRequest($requestxml);
        $data = XML_unserialize($responseXml);
        return $data;
    }

    /**
     *  delete 删除一个折扣活动
     */
    public function deleteMarkdownToEbay($data = array()) {
        $verb = 'SetPromotionalSale';
		$requestxml='';
        $requestxml.='<?xml version="1.0" encoding="utf-8"?>
					<SetPromotionalSaleRequest xmlns="urn:ebay:apis:eBLBaseComponents">
					  <RequesterCredentials>
					    <eBayAuthToken>' . $this->token . '</eBayAuthToken>
					  </RequesterCredentials>
					  <Action>Delete</Action>
					  <PromotionalSaleDetails>
					    <PromotionalSaleID>' . $data['ebay_mark_down_id'] . '</PromotionalSaleID>
					  </PromotionalSaleDetails>
					</SetPromotionalSaleRequest>';
        $session = new eBaySession('', $this->devID, $this->appID, $this->certID, $this->serverUrl, $this->compatabilityLevel, $this->siteID, $verb);
        $responseXml = $session->sendHttpRequest($requestxml);
        $data = XML_unserialize($responseXml);
        return $data;
    }

     /**
     *  折扣-绑定itemid
     */
    public function addMarkdownToEbayListing($data = array()) {
        $verb = 'SetPromotionalSaleListings';
		$requestxml='';
        $requestxml.='<?xml version="1.0" encoding="utf-8"?>
					<SetPromotionalSaleListingsRequest xmlns="urn:ebay:apis:eBLBaseComponents">
					  <RequesterCredentials>
					    <eBayAuthToken>' . $this->token . '</eBayAuthToken>
					  </RequesterCredentials>
					  <Action>Add</Action>
					  <PromotionalSaleID>' . $data['ebay_mark_down_id'] . '</PromotionalSaleID>
					  <PromotionalSaleItemIDArray>
					    <ItemID>' . $data['item_id'] . '</ItemID>
					  </PromotionalSaleItemIDArray>
					</SetPromotionalSaleListingsRequest>';
        //		return $requestxml;
        //		exit();
       // file_put_contents('D:/bangdingzhekou.txt',$requestxml);
        $session = new eBaySession('', $this->devID, $this->appID, $this->certID, $this->serverUrl, $this->compatabilityLevel, $this->siteID, $verb);
        $responseXml = $session->sendHttpRequest($requestxml);
        $data = XML_unserialize($responseXml);
        return $data['SetPromotionalSaleListingsResponse'];
        //b($data);
    }
      /**
     *  折扣-绑定itemid
     */
    public function addMarkdownToEbayListing1($data = array()) {
        $verb = 'SetPromotionalSaleListings';
		$requestxml='';
        $requestxml.='<?xml version="1.0" encoding="utf-8"?>
					<SetPromotionalSaleListingsRequest xmlns="urn:ebay:apis:eBLBaseComponents">
					  <RequesterCredentials>
					    <eBayAuthToken>' . $this->token . '</eBayAuthToken>
					  </RequesterCredentials>
					  <Action>Add</Action>
					  <PromotionalSaleID>' . $data['ebay_mark_down_id'] . '</PromotionalSaleID>
					  <PromotionalSaleItemIDArray>';
                foreach($data['item_id'] as $item)
                {
                    $requestxml.=' <ItemID>' .$item['item_id']. '</ItemID>'; 
                }
			   
				$requestxml.='</PromotionalSaleItemIDArray>
					</SetPromotionalSaleListingsRequest>';
        $session = new eBaySession('', $this->devID, $this->appID, $this->certID, $this->serverUrl, $this->compatabilityLevel, $this->siteID, $verb);
        $responseXml = $session->sendHttpRequest($requestxml);
        $data = XML_unserialize($responseXml);
        return $data['SetPromotionalSaleListingsResponse'];
        //b($data);
    }

    /**
     *  折扣-解绑itemid
     */
    public function deleteMarkdownToEbayListing($data = array()) {
            $verb = 'SetPromotionalSaleListings';
			$requestxml='';
            $requestxml.='<?xml version="1.0" encoding="utf-8"?>
					<SetPromotionalSaleListingsRequest xmlns="urn:ebay:apis:eBLBaseComponents">
					  <RequesterCredentials>
					    <eBayAuthToken>' . $this->token . '</eBayAuthToken>
					  </RequesterCredentials>
					  <Action>Delete</Action>
					  <PromotionalSaleID>' . $data['ebay_mark_down_id'] . '</PromotionalSaleID>
					  <PromotionalSaleItemIDArray>
					    <ItemID>' . $data['item_id'] . '</ItemID>
					  </PromotionalSaleItemIDArray>
					</SetPromotionalSaleListingsRequest>';         
        //a($requestxml);
       // file_put_contents('D:/'.$data['item_id'].'.txt',$requestxml);
        $session = new eBaySession('', $this->devID, $this->appID, $this->certID, $this->serverUrl, $this->compatabilityLevel, $this->siteID, $verb);
        $responseXml = $session->sendHttpRequest($requestxml);
        $data = XML_unserialize($responseXml);
        return $data['SetPromotionalSaleListingsResponse'];
    }
	 /**
     *  自动-绑定itemid
     */
    public function addMarkdownToEbayItem($data = array()) {
        $verb = 'SetPromotionalSaleListings';
        $requestxml='';
        $requestxml.='<?xml version="1.0" encoding="utf-8"?>
					<SetPromotionalSaleListingsRequest xmlns="urn:ebay:apis:eBLBaseComponents">
					  <RequesterCredentials>
					    <eBayAuthToken>' . $this->token . '</eBayAuthToken>
					  </RequesterCredentials>
					  <Action>Add</Action>
					  <PromotionalSaleID>' . $data['ebay_mark_down_id'] . '</PromotionalSaleID>
					  <PromotionalSaleItemIDArray>';
                    foreach ((array) $data['item_id'] as $key1 => $vel1) 
                    {
				     $requestxml.='<ItemID>' . $vel1['item_id'] . '</ItemID>';
                    }
			      $requestxml.='</PromotionalSaleItemIDArray>
					</SetPromotionalSaleListingsRequest>';
        $session = new eBaySession('', $this->devID, $this->appID, $this->certID, $this->serverUrl, $this->compatabilityLevel, $this->siteID, $verb);
        $responseXml = $session->sendHttpRequest($requestxml);
        $data = XML_unserialize($responseXml);
        return $data['SetPromotionalSaleListingsResponse'];
        //b($data);
    }

    /*     * **************************************UPDATE LISTING API START********************************** */

    /**
     * 描述更新
     */
    public function updateDescriptionToEbay($itemid, $description) {
        $verb = 'ReviseItem';
        $requestxml = '<?xml version="1.0" encoding="utf-8"?>
					   <ReviseItemRequest xmlns="urn:ebay:apis:eBLBaseComponents">
		               <RequesterCredentials>
	                   <eBayAuthToken>' . $this->token . '</eBayAuthToken> 
	                    </RequesterCredentials>
	                   <Item>
	                   		<ItemID>' . $itemid . '</ItemID>
	                   		<Description>' . $description . '</Description>
					   </Item>
					 </ReviseItemRequest>';
        $session = new eBaySession('', $this->devID, $this->appID, $this->certID, $this->serverUrl, $this->compatabilityLevel, $this->siteID, $verb);
        $responseXml = $session->sendHttpRequest($requestxml);
        $data = XML_unserialize($responseXml);
        return $data;
    }

    /**
     * 描述更新较少实例化的开销
     */
    public function updateDescriptionToEbayAlone($token, $siteid, $itemid, $description) {
        $verb = 'ReviseItem';
        $requestxml = '<?xml version="1.0" encoding="utf-8"?>
					   <ReviseItemRequest xmlns="urn:ebay:apis:eBLBaseComponents">
		               <RequesterCredentials>
	                   <eBayAuthToken>' . $token . '</eBayAuthToken> 
	                    </RequesterCredentials>
	                   <Item>
	                   		<ItemID>' . $itemid . '</ItemID>
	                   		<Description>' . $description . '</Description>
	                   		<DescriptionReviseMode>Replace</DescriptionReviseMode>
					   </Item>
					 </ReviseItemRequest>';
        $session = new eBaySession('', $this->devID, $this->appID, $this->certID, $this->serverUrl, $this->compatabilityLevel, $siteid, $verb);
        $responseXml = $session->sendHttpRequest($requestxml);
        $data = XML_unserialize($responseXml);
        return $data;
    }

    public function updateStoreCategory($params) {
        $verb = 'ReviseItem';
        $requestxml = '<?xml version="1.0" encoding="utf-8"?>
		   <ReviseItemRequest xmlns="urn:ebay:apis:eBLBaseComponents">
           <RequesterCredentials>
           <eBayAuthToken>' . $params['ebay_token'] . '</eBayAuthToken> 
            </RequesterCredentials><Item>';
        $requestxml.='<ItemID>' . $params['item_id'] . '</ItemID>';
        //商店 一级分类
        if ($params['shop_category1_name'] || $params['shop_category2_name'])
            $requestxml.='<Storefront>';

        if ($params['shop_category1_name'])
            $requestxml.='<StoreCategoryID>' . $params['shop_category1_id'] . '</StoreCategoryID><StoreCategoryName>' . $params['shop_category1_name'] . '</StoreCategoryName>';

        //商店  二级分类
        if ($params['shop_category2_name'])
            $requestxml.=$requestxml.='<StoreCategory2ID>' . $params['shop_category1_id'] . '</StoreCategory2ID><StoreCategory2Name>' . $params['shop_category1_name'] . '</StoreCategory2Name>';
        if ($params['shop_category1_name'] || $params['shop_category2_name'])
            $requestxml.='</Storefront>';

        $requestxml.='</Item></ReviseItemRequest>';
        $session = new eBaySession('', $this->devID, $this->appID, $this->certID, $this->serverUrl, $this->compatabilityLevel, $this->siteID, $verb);
        $responseXml = $session->sendHttpRequest($requestxml);
        $data = XML_unserialize($responseXml);
        unset($data['ReviseItemResponse attr']);
        if ($data['ReviseItemResponse']['Ack'] == 'Success')
            unset($data['ReviseItemResponse']['Fees']);
        return $data;
    }

    /*     * **************************************UPDATE LISTING API END********************************** */

    /**
     *  描述添加
     */
    public function adddescription($itemid, $description) {
        $verb = 'AddToItemDescription';
        $requestxml = '<?xml version="1.0" encoding="utf-8"?>
				<AddToItemDescriptionRequest xmlns="urn:ebay:apis:eBLBaseComponents">
					<RequesterCredentials>
	            <eBayAuthToken>' . $this->token . '</eBayAuthToken> 
	            </RequesterCredentials>
	            	  <ItemID>' . $itemid . '</ItemID>
				  <Description>' . $description . '</Description>
				</AddToItemDescriptionRequest>';

        $session = new eBaySession('', $this->devID, $this->appID, $this->certID, $this->serverUrl, $this->compatabilityLevel, $this->siteID, $verb);
        $responseXml = $session->sendHttpRequest($requestxml);
        $data = XML_unserialize($responseXml);
        return $data;
    }

    //二次刊登
    public function seconditem($days, $itemid, $username) {
        $verb = 'AddSecondChanceItem';
        $requestxml = '<?xml version="1.0" encoding="utf-8"?>
						<AddSecondChanceItemRequest xmlns="urn:ebay:apis:eBLBaseComponents">
						  <RequesterCredentials>
				            <eBayAuthToken>' . $this->token . '</eBayAuthToken> 
				            </RequesterCredentials>
						  <Duration>' . $days . '</Duration>
						  <ItemID>' . $itemid . '</ItemID>
						  <RecipientBidderUserID>' . $username . '</RecipientBidderUserID>
						</AddSecondChanceItemRequest>';
        $session = new eBaySession('', $this->devID, $this->appID, $this->certID, $this->serverUrl, $this->compatabilityLevel, $this->siteID, $verb);
        $responseXml = $session->sendHttpRequest($requestxml);
        $data = XML_unserialize($responseXml);
        return $data;
    }

    /*     * *******************************************item修改接口 数量 价格 标题************************************ */

    public function revise($params) {
        $verb = 'ReviseItem';
        $requestxml = '<?xml version="1.0" encoding="utf-8"?>
					   <ReviseItemRequest xmlns="urn:ebay:apis:eBLBaseComponents">
		               <RequesterCredentials>
	                   <eBayAuthToken>' . $this->token . '</eBayAuthToken> 
	                    </RequesterCredentials>
	                   <Item>
	                  <ItemID>' . $params['itemid'] . '</ItemID>
					    <SKU>' . $params['goods_sku'] . '</SKU>
					    <StartPrice>' . $params['listing_price'] . '</StartPrice>
					    <Quantity>' . $params['listing_qty'] . '</Quantity>
					    <Title>' . $params['listing_title1'] . '</Title>
					    </Item>
					 </ReviseItemRequest>';
        $session = new eBaySession('', $this->devID, $this->appID, $this->certID, $this->serverUrl, $this->compatabilityLevel, $this->siteID, $verb);
        $responseXml = $session->sendHttpRequest($requestxml);
        $data = XML_unserialize($responseXml);
        return $data;
    }

    /*     * *******************************************推送接口************************************ */

    public function setnotification($token, $site_id) {
        $verb = 'SetNotificationPreferences';
        $requestxml = '<?xml version="1.0" encoding="utf-8"?>
			<SetNotificationPreferencesRequest xmlns="urn:ebay:apis:eBLBaseComponents">
				<RequesterCredentials>
					 <eBayAuthToken>' . $token . '</eBayAuthToken> 
				</RequesterCredentials>
			<ApplicationDeliveryPreferences>
				<ApplicationEnable>Enable</ApplicationEnable>
				<ApplicationURL>http://220.248.107.42:56453/Notifications/listener.php</ApplicationURL>
				<AlertEnable>Disable</AlertEnable>
				<DeviceType>Platform</DeviceType>
			</ApplicationDeliveryPreferences>
			<UserDeliveryPreferenceArray>
				<NotificationEnable>
					<EventType>ItemClosed</EventType><EventEnable>Enable</EventEnable>					
					<EventType>AuctionCheckoutComplete</EventType><EventEnable>Enable</EventEnable>
					<EventType>FixedPriceTransaction</EventType><EventEnable>Enable</EventEnable>
					<EventType>EndOfAuction</EventType><EventEnable>Enable</EventEnable>
				</NotificationEnable>
			</UserDeliveryPreferenceArray>
			</SetNotificationPreferencesRequest>';
        $session = new eBaySession('', $this->devID, $this->appID, $this->certID, $this->serverUrl, $this->compatabilityLevel, $site_id, $verb);
        $responseXml = $session->sendHttpRequest($requestxml);
        $data = XML_unserialize($responseXml);
        return $data;
    }

    public function getnotification($token, $site_id) {
        $verb = 'GetNotificationPreferences';
        $requestxml = '<?xml version="1.0" encoding="utf-8"?>
			<GetNotificationPreferencesRequest xmlns="urn:ebay:apis:eBLBaseComponents">
			<RequesterCredentials>
			<eBayAuthToken>' . $token . '</eBayAuthToken>
			</RequesterCredentials>
			</GetNotificationPreferencesRequest>';
        $session = new eBaySession('', $this->devID, $this->appID, $this->certID, $this->serverUrl, $this->compatabilityLevel, $site_id, $verb);
        $responseXml = $session->sendHttpRequest($requestxml);
        $data = XML_unserialize($responseXml);
        return $data;
    }

    /*     * *****************************************************LISTING重新刊登************************************************** */

//修改价格
    public function ReviseItem($itemid, $startprice) {
        $verb = 'ReviseItem';
        $requestxml = '<?xml version="1.0" encoding="utf-8"?>
					<ReviseItemRequest xmlns="urn:ebay:apis:eBLBaseComponents">
					  <RequesterCredentials>
					 <eBayAuthToken>' . $this->token . '</eBayAuthToken> 
					  </RequesterCredentials>
					  <Item>
					    <ItemID>' . $itemid . '</ItemID>
					    <StartPrice>' . $startprice . '</StartPrice >
					  </Item>
					</ReviseItemRequest>';
        $session = new eBaySession('', $this->devID, $this->appID, $this->certID, $this->serverUrl, $this->compatabilityLevel, $this->siteID, $verb);
        $responseXml = $session->sendHttpRequest($requestxml);
        $data = XML_unserialize($responseXml);
        return $data;
        //return $data['ReviseItemResponse'];
        //return $data['ReviseItemResponse']['Ack'];
    }

//刷新付款方式
    public function refreshEbayPayment($token, $siteid, $itemid, $paypal) {
        $verb = 'ReviseItem';
        $requestxml = '<?xml version="1.0" encoding="utf-8"?>
					   <ReviseItemRequest xmlns="urn:ebay:apis:eBLBaseComponents">
		               <RequesterCredentials>
	                   <eBayAuthToken>' . $token . '</eBayAuthToken> 
	                    </RequesterCredentials>
	                   <Item>
	                  <ItemID>' . $itemid . '</ItemID>  
					  <PaymentMethods>PayPal</PaymentMethods>
					<PayPalEmailAddress>' . $paypal . '</PayPalEmailAddress>
  					</Item>
					 </ReviseItemRequest>';
        $session = new eBaySession('', $this->devID, $this->appID, $this->certID, $this->serverUrl, $this->compatabilityLevel, $siteid, $verb);
        $responseXml = $session->sendHttpRequest($requestxml);
        $data = XML_unserialize($responseXml);
        unset($data['ReviseItemResponse attr']);
        if (isset($data['ReviseItemResponse']['Fees']))
            unset($data['ReviseItemResponse']['Fees']);
        return $data['ReviseItemResponse'];
    }

//更新价格
    public function updateEbayPrice($token, $itemid, $startprice) {
        $verb = 'ReviseItem';
        $requestxml = '<?xml version="1.0" encoding="utf-8"?>
					<ReviseItemRequest xmlns="urn:ebay:apis:eBLBaseComponents">
					  <RequesterCredentials>
					 <eBayAuthToken>' . $token . '</eBayAuthToken> 
					  </RequesterCredentials>
					  <Item>
					    <ItemID>' . $itemid . '</ItemID>
					    <StartPrice>' . $startprice . '</StartPrice >
					  </Item>
					</ReviseItemRequest>';
        $session = new eBaySession('', $this->devID, $this->appID, $this->certID, $this->serverUrl, $this->compatabilityLevel, $this->siteID, $verb);
        $responseXml = $session->sendHttpRequest($requestxml);
        $data = XML_unserialize($responseXml);
        return $data['ReviseItemResponse'];
    }

//更新数量
    public function updateEbayQty($token, $itemid, $qty) {
        $verb = 'ReviseItem';
        $requestxml = '<?xml version="1.0" encoding="utf-8"?>
					<ReviseItemRequest xmlns="urn:ebay:apis:eBLBaseComponents">
					  <RequesterCredentials>
					 <eBayAuthToken>' . $token . '</eBayAuthToken> 
					  </RequesterCredentials>
					  <Item>
					    <ItemID>' . $itemid . '</ItemID>
					    <Quantity>' . $qty . '</Quantity >
					  </Item>
					</ReviseItemRequest>';
        $session = new eBaySession('', $this->devID, $this->appID, $this->certID, $this->serverUrl, $this->compatabilityLevel, $this->siteID, $verb);
        $responseXml = $session->sendHttpRequest($requestxml);
        $data = XML_unserialize($responseXml);
        unset($data['ReviseItemResponse attr']);
        if (isset($data['ReviseItemResponse']['Fees']))
            unset($data['ReviseItemResponse']['Fees']);
        return $data['ReviseItemResponse'];
    }

    //更新多属性价格
    public function updateEbaydsPrice($token, $itemid, $startprice, $sku) {
        $verb = 'ReviseInventoryStatus';
        $requestxml = '<?xml version="1.0" encoding="utf-8"?>
				<ReviseInventoryStatusRequest xmlns="urn:ebay:apis:eBLBaseComponents">
					  <RequesterCredentials>
					 <eBayAuthToken>' . $token . '</eBayAuthToken> 
					  </RequesterCredentials>
                      <InventoryStatus>
                        <ItemID>' . $itemid . '</ItemID>
                        <SKU>' . $sku . '</SKU>
                        <StartPrice>' . $startprice . '</StartPrice>
                      </InventoryStatus>
					</ReviseInventoryStatusRequest>';
        $session = new eBaySession('', $this->devID, $this->appID, $this->certID, $this->serverUrl, $this->compatabilityLevel, $this->siteID, $verb);
        $responseXml = $session->sendHttpRequest($requestxml);
        $data = XML_unserialize($responseXml);
        unset($data['ReviseInventoryStatusResponse attr']);
        return $data;
    }

    //更新多属性数量
    public function updatePropertyQty($token, $itemid, $qty, $sku) {
        $verb = 'ReviseInventoryStatus';
        $requestxml = '<?xml version="1.0" encoding="utf-8"?>
				<ReviseInventoryStatusRequest xmlns="urn:ebay:apis:eBLBaseComponents">
					  <RequesterCredentials>
					 <eBayAuthToken>' . $token . '</eBayAuthToken> 
					  </RequesterCredentials>
                      <InventoryStatus>
                        <ItemID>' . $itemid . '</ItemID>
                        <Quantity>' . $qty . '</Quantity>
                        <SKU>' . $sku . '</SKU>
                      </InventoryStatus>
					</ReviseInventoryStatusRequest>';
        $session = new eBaySession('', $this->devID, $this->appID, $this->certID, $this->serverUrl, $this->compatabilityLevel, $this->siteID, $verb);
        $responseXml = $session->sendHttpRequest($requestxml);
        $data = XML_unserialize($responseXml);
        unset($data['ReviseInventoryStatusResponse attr']);
        return $data;
    }

//更新兼容属性	
    public function updateEbayCompatibilities($token, $itemid, $data) {
        $verb = 'ReviseItem';
        $requestxml = '<?xml version="1.0" encoding="utf-8"?>
					<ReviseItemRequest xmlns="urn:ebay:apis:eBLBaseComponents">
					  <RequesterCredentials>
					 <eBayAuthToken>' . $token . '</eBayAuthToken> 
					  </RequesterCredentials>
					  <Item>
					    <ItemID>' . $itemid . '</ItemID>';
        //兼容属性
        $requestxml .= '<ItemCompatibilityList>';
        foreach ($data as $compatibilitiyValue) {
            $requestxml.='<Compatibility>';
            foreach ($compatibilitiyValue as $_N => $_V) {
                $_V = htmlentities($_V);
                $requestxml.="<NameValueList><Name>{$_N}</Name><Value>{$_V}</Value></NameValueList>";
            }
            $requestxml.='</Compatibility>';
        }

        $requestxml.='</ItemCompatibilityList>';
        $requestxml.='</Item></ReviseItemRequest>';
        $session = new eBaySession('', $this->devID, $this->appID, $this->certID, $this->serverUrl, $this->compatabilityLevel, $this->siteID, $verb);
        $responseXml = $session->sendHttpRequest($requestxml);
        $data = XML_unserialize($responseXml);
        return $data['ReviseItemResponse'];
    }

//更新offer
    public function updateEbayOfferStatus($token, $itemid, $off = true) {
        $verb = 'ReviseItem';
        $requestxml = '<?xml version="1.0" encoding="utf-8"?>
					<ReviseItemRequest xmlns="urn:ebay:apis:eBLBaseComponents">
					  <RequesterCredentials>
					 <eBayAuthToken>' . $token . '</eBayAuthToken> 
					  </RequesterCredentials>
					  <Item>
					    <ItemID>' . $itemid . '</ItemID>
					    <BestOfferDetails>
					      <BestOfferEnabled>' . $off . '</BestOfferEnabled>
					    </BestOfferDetails>
					  </Item>
					</ReviseItemRequest>';
        $session = new eBaySession('', $this->devID, $this->appID, $this->certID, $this->serverUrl, $this->compatabilityLevel, $this->siteID, $verb);
        $responseXml = $session->sendHttpRequest($requestxml);
        $data = XML_unserialize($responseXml);
        return $data['ReviseItemResponse'];
    }

    /*     * *****************************************************兼容属性接口区************************************** */

    /**
     * 获取分类详情是否支持兼容属性
     * http://developer.ebay.com/DevZone/XML/docs/Reference/eBay/GetCategoryFeatures.html#samplespecific
     */
    public function GetCategoryFeatures($categoryid, $site_id = 0) {
        $verb = 'GetCategoryFeatures';
        $requestxml = '<?xml version="1.0" encoding="utf-8"?>
		<GetCategoryFeaturesRequest xmlns="urn:ebay:apis:eBLBaseComponents">
		  <RequesterCredentials>
		    <eBayAuthToken>' . $this->token . '</eBayAuthToken>
		  </RequesterCredentials>
		  <CategoryID>' . $categoryid . '</CategoryID>
		  <FeatureID>CompatibilityEnabled</FeatureID>
		  <!--FeatureID>AdditionalCompatibilityEnabled</FeatureID-->
		  <!--FeatureID>MinCompatibleApplications</FeatureID-->
		  <!--FeatureID>MaxCompatibleApplications</FeatureID-->
		  <DetailLevel>ReturnAll</DetailLevel>
		</GetCategoryFeaturesRequest>';
        $session = new eBaySession('', $this->devID, $this->appID, $this->certID, $this->serverUrl, $this->compatabilityLevel, $site_id, $verb);
        $responseXml = $session->sendHttpRequest($requestxml);
        $data = XML_unserialize($responseXml);
        return $data['GetCategoryFeaturesResponse'];
    }

    /**
     * 返回所有的属性
     * @return [type] [description]
     */
    public function getCompatibilitySearchValues($make, $categoryid = 33709) {
        $verb = 'getCompatibilitySearchValues';
        $requestxml = '<?xml version="1.0" encoding="UTF-8"?>
			<getCompatibilitySearchValuesRequest xmlns="http://www.ebay.com/marketplace/marketplacecatalog/v1/services">
			  <categoryId>' . $categoryid . '</categoryId>
			  <listFormatOnly>true</listFormatOnly>
			  <propertyName>' . $make . '</propertyName>
			</getCompatibilitySearchValuesRequest>';
        $API = new ProductServiceApi($verb, $globalID = 'EBAY-MOTOR', $appid = $this->appID);
        $responseXml = $API->sendHTTPRequest($requestxml, 0);
        $data = XML_unserialize($responseXml);
        return $data['getCompatibilitySearchValuesResponse']['propertyValues'];
    }

    /**
     * 返回指定属性
     * @return [type] [description]
     */
    public function getCompatibilitySearchValuesArg($condition, $make, $categoryid = 33709) {
        $verb = 'getCompatibilitySearchValues';
        $requestxml = '<?xml version="1.0" encoding="UTF-8"?>
			<getCompatibilitySearchValuesRequest xmlns="http://www.ebay.com/marketplace/marketplacecatalog/v1/services">
			  <categoryId>' . $categoryid . '</categoryId>'
                . $condition .
                '<listFormatOnly>true</listFormatOnly>
			  <propertyName>' . $make . '</propertyName>
			</getCompatibilitySearchValuesRequest>';
        $API = new ProductServiceApi($verb, $globalID = 'EBAY-MOTOR', $appid = $this->appID);
        $responseXml = $API->sendHTTPRequest($requestxml, 0);
        $data = XML_unserialize($responseXml);
        if (!isset($data['getCompatibilitySearchValuesResponse']))
            return false;
        if ($data['getCompatibilitySearchValuesResponse']['ack'] === 'Success') {
            return isset($data['getCompatibilitySearchValuesResponse']['propertyValues']) ? $data['getCompatibilitySearchValuesResponse']['propertyValues'] : true;
        }
        return false;
    }

    /**
     * 拉出一级
     */
    public function getCompatibilitySearchValuesArgTT($make, $model, $year, $trim, $categoryid = 33709) {
        $verb = 'getCompatibilitySearchValues';
        $requestxml = '<?xml version="1.0" encoding="UTF-8"?>
			<getCompatibilitySearchValuesRequest xmlns="http://www.ebay.com/marketplace/marketplacecatalog/v1/services">
			  <categoryId>' . $categoryid . '</categoryId>
				<propertyFilter>
		            <propertyName>Make</propertyName>
		            <value>
		              <text>
		                <value>' . htmlspecialchars($make) . '</value>
		              </text>
		            </value>
		          </propertyFilter>				
		          <propertyFilter>
		            <propertyName>Model</propertyName>
		            <value>
		              <text>
		                <value>' . htmlspecialchars($model) . '</value>
		              </text>
		            </value>
		          </propertyFilter>			          
		          <propertyFilter>
		            <propertyName>Year</propertyName>
		            <value>
		              <text>
		                <value>' . htmlspecialchars($year) . '</value>
		              </text>
		            </value>
		          </propertyFilter>	
		          <propertyFilter>
		            <propertyName>Trim</propertyName>
		            <value>
		              <text>
		                <value>' . htmlspecialchars($trim) . '</value>
		              </text>
		            </value>
		          </propertyFilter>	 		  		          
			  <listFormatOnly>true</listFormatOnly>
			  <propertyName>Engine</propertyName>
			</getCompatibilitySearchValuesRequest>';
        $API = new ProductServiceApi($verb, $globalID = 'EBAY-MOTOR', $appid = $this->appID);
        $responseXml = $API->sendHTTPRequest($requestxml, 0);
        $data = XML_unserialize($responseXml);
        if ($data['getCompatibilitySearchValuesResponse']['ack'] === 'Success') {
            return isset($data['getCompatibilitySearchValuesResponse']['propertyValues']) ? $data['getCompatibilitySearchValuesResponse']['propertyValues'] : true;
        }
        return false;
    }

    /**
     * 获取制定分类信息 pid epid
     * @return [type] [description]
     */
    public function findProductsByCompatibility() {
        $verb = 'findProductsByCompatibility';
        $requestxml = '<?xml version="1.0" encoding="UTF-8"?>
			<findProductsByCompatibilityRequest xmlns="http://www.ebay.com/marketplace/marketplacecatalog/v1/services">
			   <productByCompatibilitySearch>
			      <applicationPropertyFilter>
			         <propertyFilter>
			            <propertyName>Make</propertyName>
			            <value>
			               <text>
			                  <value>Honda</value>
			               </text>
			            </value>
			         </propertyFilter>
			      </applicationPropertyFilter>
			      <productSearch>
			         <invocationId>6521472365</invocationId>
			         <categoryId>33709</categoryId>
			      </productSearch>
			   </productByCompatibilitySearch>
			</findProductsByCompatibilityRequest>';
        $API = new ProductServiceApi($verb, $globalID = 'EBAY-MOTOR', $appid = $this->appID);
        $responseXml = $API->sendHTTPRequest($requestxml);
        $data = XML_unserialize($responseXml);
        a($data);
    }

    /**
     * 
     */
    public function getProductCompatibilities() {
        $verb = 'getProductCompatibilities';
        $requestxml = '<?xml version="1.0" encoding="UTF-8"?>
			<getProductCompatibilitiesRequest 
			xmlns="http://www.ebay.com/marketplace/marketplacecatalog/v1/services">
			   <productIdentifier>
			      <ePID>136975614</ePID>
			   </productIdentifier>
			   <datasetPropertyName>All</datasetPropertyName>
			   <paginationInput>
			    <entriesPerPage>50</entriesPerPage>
			    <!--pageNumber>3</pageNumber-->
			   </paginationInput>
			</getProductCompatibilitiesRequest>';
        $API = new ProductServiceApi($verb, $globalID = 'EBAY-MOTOR', $appid = $this->appID);
        $responseXml = $API->sendHTTPRequest($requestxml);
        $data = XML_unserialize($responseXml);
        a($data['getProductCompatibilitiesResponse']);
    }

    /**
     *  Search attrubte name
     * 
     */
    public function getProductSearchNames() {
        $verb = 'getProductSearchNames';
        $requestxml = '<?xml version="1.0" encoding="UTF-8"?>
		<getProductSearchNamesRequest xmlns="http://www.ebay.com/marketplace/marketplacecatalog/v1/services">
		   <categoryId>33550</categoryId>
		   <dataset>Sortable</dataset>
		</getProductSearchNamesRequest>';
        $API = new ProductServiceApi($verb, $globalID = 'EBAY-MOTOR', $appid = $this->appID);
        $responseXml = $API->sendHTTPRequest($requestxml, 0);
        $data = XML_unserialize($responseXml);
    }
	  public function getItems($itemid) {
			$verb = 'GetItem';
			$requestxml = '<?xml version="1.0" encoding="utf-8"?>
			<GetItemRequest xmlns="urn:ebay:apis:eBLBaseComponents">
			  <RequesterCredentials>
				<eBayAuthToken>' . $this->token . '</eBayAuthToken>
			  </RequesterCredentials>
					<ItemID>' . $itemid . '</ItemID>
					<OutputSelector>Item</OutputSelector>
			</GetItemRequest>';
			$session = new eBaySession('', $this->devID, $this->appID, $this->certID, $this->serverUrl, $this->compatabilityLevel, $this->siteID, $verb);
			$responseXml = $session->sendHttpRequest($requestxml);
			$data = XML_unserialize($responseXml);
			return $data['GetItemResponse'];
		}

    /*     * *****************************************************Ebay 大数据LMS 接口区************************************************** */

    /**
     * 大文件下载
     */
    public function GetEbayDownloadFile($data, $siteid) {
        $verb = 'downloadFile';
        $requestxml = '<?xml version="1.0" encoding="utf-8"?>
		<downloadFileRequest xmlns:sct="http://www.ebay.com/soaframework/common/types" xmlns="http://www.ebay.com/marketplace/services">
			  <taskReferenceId>' . $data['TaskReferenceID'] . '</taskReferenceId>
			  <fileReferenceId>' . $data['FileReferenceID'] . '</fileReferenceId>
			</downloadFileRequest>';
        //接口变换
        $LMS = new LargeMerchantServiceSession($this->token);
        $responseXml = $LMS->sendHTTPRequest($requestxml);
        $return = new DOMUtils();
        return $return->writeFile($responseXml, $siteid);
        //a($responseXml);
    }

    public function GetItemRecommendations() {
        $verb = 'GetItemRecommendations';
        $requestxml = '<?xml version="1.0" encoding="utf-8"?>
		<GetItemRecommendationsRequest xmlns="urn:ebay:apis:eBLBaseComponents">
		  <GetRecommendationsRequestContainer>
		    <RecommendationEngine>ItemSpecifics</RecommendationEngine>
		    <Item>
		      <PrimaryCategory>
		        <CategoryID>20668</CategoryID>
		      </PrimaryCategory>
		      <Title>KitchenAid Blender</Title>
		    </Item>
		    <IncludeConfidence>true</IncludeConfidence>
		    <CorrelationID>1</CorrelationID>
		  </GetRecommendationsRequestContainer>
		  <RequesterCredentials>
		    <eBayAuthToken>' . $this->token . '</eBayAuthToken>
		  </RequesterCredentials>
		</GetItemRecommendationsRequest>';
        $session = new eBaySession('', $this->devID, $this->appID, $this->certID, $this->serverUrl, $this->compatabilityLevel, $this->siteID, $verb);
        $responseXml = $session->sendHttpRequest($requestxml);
        $data = XML_unserialize($responseXml);
        return $data;
    }

    /*     * ***********************************************************脚本状态同步****************************************************** */

    public function getEbayListingSelling($startTime, $endTime, $pageNum = 1) {
        $verb = 'GetSellerList';
        $requestxml = '<?xml version="1.0" encoding="utf-8"?>
			<GetSellerListRequest xmlns="urn:ebay:apis:eBLBaseComponents">
			  <RequesterCredentials>
			    <eBayAuthToken>' . $this->token . '</eBayAuthToken>
			  </RequesterCredentials>
			  <GranularityLevel>Coarse</GranularityLevel> 
			  <StartTimeFrom>' . $startTime . '</StartTimeFrom> 
			  <StartTimeTo>' . $endTime . '</StartTimeTo> 
			  <Pagination> 
			    <EntriesPerPage>200</EntriesPerPage> 
			    <pageNumber>' . $pageNum . '</pageNumber>
			  </Pagination> 
			  <OutputSelector>ItemArray.Item.ItemID,ItemArray.Item.SKU,ItemArray.Item.SellingStatus.QuantitySold,ItemArray.Item.Site,ItemArray.Item.Quantity,ItemArray.Item.SellingStatus.ListingStatus,PageNumber,PaginationResult.TotalNumberOfPages,PaginationResult.TotalNumberOfEntries</OutputSelector>
			</GetSellerListRequest>';
        $session = new eBaySession('', $this->devID, $this->appID, $this->certID, $this->serverUrl, $this->compatabilityLevel, $this->siteID, $verb);
        $responseXml = $session->sendHttpRequest($requestxml);
        $data = XML_unserialize($responseXml);
        return $data['GetSellerListResponse'];
        /*
          ItemArray.Item.ItemID
          ItemArray.Item.SKU
          ItemArray.Item.Quantity
          ItemArray.Item.SellingStatus.QuantitySold
          ItemArray.Item.SellingStatus.ListingStatus  | Completed/Active
          PaginationResult.TotalNumberOfEntries
          PaginationResult.TotalNumberOfPages
         */
    }

    /*     * ***********************************************************销量拉取****************************************************** */

    /**
     * [getEbayListingSales 90天]
     * @param  [type]  $startTime [description]
     * @param  [type]  $endTime   [description]
     * @param  integer $pageNum   [description]
     * @return [type]             [description]
     */
    public function GetEbayOrderSales($startTime, $endTime, $pageNum = 1) {
        $verb = 'GetOrders';
        $requestxml = '<?xml version="1.0" encoding="utf-8"?>
			<GetOrdersRequest xmlns="urn:ebay:apis:eBLBaseComponents">
			  <RequesterCredentials>
			    <eBayAuthToken>' . $this->token . '</eBayAuthToken>
			  </RequesterCredentials>
			  <CreateTimeFrom>' . $startTime . '</CreateTimeFrom>
			  <CreateTimeTo>' . $endTime . '</CreateTimeTo>		
			  <Pagination> 
			    <EntriesPerPage>200</EntriesPerPage> 
			    <pageNumber>' . $pageNum . '</pageNumber>
			  </Pagination> 	  
			  <OrderRole>Seller</OrderRole>
			  <OrderStatus>All</OrderStatus>
  			  <OutputSelector>PageNumber,PaginationResult.TotalNumberOfPages,PaginationResult.TotalNumberOfEntries,OrderArray.Order.TransactionArray.Transaction.CreatedDate,OrderArray.Order.TransactionArray.Transaction.QuantityPurchased,OrderArray.Order.TransactionArray.Transaction.Item.ItemID</OutputSelector>
			</GetOrdersRequest>';
        $session = new eBaySession('', $this->devID, $this->appID, $this->certID, $this->serverUrl, $this->compatabilityLevel, $this->siteID, $verb);
        $responseXml = $session->sendHttpRequest($requestxml);
        $data = XML_unserialize($responseXml);
        return $data['GetOrdersResponse'];
        /**
         * OrderArray.Order.OrderStatus
         */
    }

    public function GetEbayOrderTransactions($startTime, $endTime, $pageNum = 1) {
        $verb = 'GetSellerTransactions';
        $requestxml = '<?xml version="1.0" encoding="utf-8"?>
			<GetSellerTransactionsRequest xmlns="urn:ebay:apis:eBLBaseComponents">
			  <DetailLevel>ReturnAll</DetailLevel>
			  <RequesterCredentials>
			    <eBayAuthToken>' . $this->token . '</eBayAuthToken>
			  </RequesterCredentials>
			    <ModTimeFrom>2013-01-01T18:28:52.799Z</ModTimeFrom>
  				<ModTimeTo>2013-01-30T18:28:52.799Z</ModTimeTo>
			</GetSellerTransactionsRequest>';
        $session = new eBaySession('', $this->devID, $this->appID, $this->certID, $this->serverUrl, $this->compatabilityLevel, $this->siteID, $verb);
        $responseXml = $session->sendHttpRequest($requestxml);
        $data = XML_unserialize($responseXml);
        a($data);
        return $data['GetSellerListResponse'];
    }

    /**
     * 获取最近30天的效率
     * @param [type]  $startTime [description]
     * @param [type]  $endTime   [description]
     * @param integer $pageNum   [description]
     */
    public function GetEbayItemTransactions($ebay_token, $startTime, $endTime, $itemid, $pageNum = 1) {
        $verb = 'GetItemTransactions';
        $requestxml = '<?xml version="1.0" encoding="utf-8"?>
			<GetItemTransactionsRequest xmlns="urn:ebay:apis:eBLBaseComponents">
			  <RequesterCredentials>
			    <eBayAuthToken>' . $ebay_token . '</eBayAuthToken>
			  </RequesterCredentials>
			    <ModTimeFrom>' . $startTime . '</ModTimeFrom>
  				<ModTimeTo>' . $endTime . '</ModTimeTo>
			  <ItemID>' . $itemid . '</ItemID>
			  <Pagination> 
			    <EntriesPerPage>200</EntriesPerPage> 
			    <pageNumber>' . $pageNum . '</pageNumber>
			  </Pagination> 
			  <OutputSelector>PageNumber,PaginationResult.TotalNumberOfPages,PaginationResult.TotalNumberOfEntries,TransactionArray.Transaction.QuantityPurchased,TransactionArray.Transaction.CreatedDate,Item.SellingStatus.QuantitySold,Item.SellingStatus.ListingStatus</OutputSelector>
			</GetItemTransactionsRequest>';
        $session = new eBaySession('', $this->devID, $this->appID, $this->certID, $this->serverUrl, $this->compatabilityLevel, $this->siteID, $verb);
        $responseXml = $session->sendHttpRequest($requestxml);
        $data = XML_unserialize($responseXml);
        return $data['GetItemTransactionsResponse'];
    }

    //批量更新接口
    public function linebatch($params = array()) {
        $verb = 'ReviseItem';
        $requestxml = '<?xml version="1.0" encoding="utf-8"?>
                     <ReviseItemRequest xmlns="urn:ebay:apis:eBLBaseComponents">
                     <RequesterCredentials>
                     <eBayAuthToken>' . $this->token . '</eBayAuthToken> 
                     </RequesterCredentials>
                     <Item>
                    <ItemID>' . $params['itemid'] . '</ItemID>';
        if (!empty($params['line']['content']['goods_sku'])) {
            $requestxml.='<SKU>' . $params['line']['content']['goods_sku'] . '</SKU>';
        }

        if (!empty($params['line']['content']['listing_qty'])) {
            $requestxml.='<Quantity>' . $params['line']['content']['listing_qty'] . '</Quantity>';
        }
        if (!empty($params['line']['description'])) {
            $requestxml.='<description>' . $params['descritions'] . '</description>';
        }
        if (!empty($params['line']['content']['cover_picture'])) {
            $requestxml.='<PictureDetails><PictureURL>' . $params['line']['content']['cover_picture'] . '</PictureURL></PictureDetails>';
        }
        if (!empty($params['line']['content']['handling_time'])) {

            $requestxml.='<DispatchTimeMax>' . $params['line']['content']['handling_time'] . '</DispatchTimeMax>';
        }
        if (!empty($params['line']['content']['location_id'])) {

            $requestxml.='<PostalCode>' . $params['line']['content']['location_id']['location_code'] . '</PostalCode>';
        }


        if (!empty($params['line']['content']['payment_id'])) {
            $requestxml.=' <PayPalEmailAddress>' . $params['line']['content']['payment_id'] . '</PayPalEmailAddress>';
        }
        if (!empty($params['line']['content']['listing_category1'])) {
            $requestxml.='<PrimaryCategory>
				    <CategoryID>' . $params['line']['content']['listing_category1'] . '</CategoryID>
				    </PrimaryCategory>';
        }
        if (!empty($params['line']['content']['shop_category1']) || !empty($params['line']['content']['shop_category2'])) {
            $requestxml.='<Storefront>';
        }
        if (!empty($params['line']['content']['shop_category1'])) {
            $requestxml.='<StoreCategoryID>' . $params['line']['content']['shop_category1'] . '</StoreCategoryID>';
        }
        //商店  二级分类
        if (!empty($params['line']['content']['shop_category2'])) {
            $requestxml.='<StoreCategory2ID>' . $params['line']['content']['shop_category2'] . '</StoreCategory2ID>';
        }
        if (!empty($params['line']['content']['shop_category1']) || !empty($params['line']['content']['shop_category2'])) {
            $requestxml.='</Storefront>';
        }
        //ebay 二级分类
        if (!empty($params['line']['content']['listing_category2'])) {
            $requestxml.='<SecondaryCategory>
						<CategoryID>' . $params['line']['content']['listing_category2'] . '</CategoryID>
				    </SecondaryCategory>';
        }
        //退货政策
        if (!empty($params['line']['content']['return_id'])) {
            $requestxml.='
				    <ReturnPolicy>
				      <ReturnsAcceptedOption>ReturnsAccepted</ReturnsAcceptedOption>
				      <RefundOption>' . $params['line']['content']['return_id']['method_char'] . '</RefundOption>
				      <ReturnsWithinOption>Days_' . $params['line']['content']['return_id']['days_char'] . '</ReturnsWithinOption>
				      <Description>' . $params['line']['content']['return_id']['return_details'] . '</Description>
				      <ShippingCostPaidByOption>' . $params['line']['content']['shippingfree'] . '</ShippingCostPaidByOption>
				    </ReturnPolicy>';
        }
        $requestxml.= '
                    </Item>
                    </ReviseItemRequest>';
        $session = new eBaySession('', $this->devID, $this->appID, $this->certID, $this->serverUrl, $this->compatabilityLevel, $this->siteID, $verb);
        $responseXml = $session->sendHttpRequest($requestxml);
        $data = XML_unserialize($responseXml);
        //	return $data['ReviseItemResponse']['Ack'];
        return $data;
    }

    //批量跟新运输选项接口
    public function transport($params, $shipping) {
        $verb = 'ReviseItem';
        $requestxml = '';
        $requestxml.='<?xml version="1.0" encoding="utf-8"?>
					<ReviseItemRequest xmlns="urn:ebay:apis:eBLBaseComponents">
					  <RequesterCredentials>
					 <eBayAuthToken>' . $this->token . '</eBayAuthToken> 
					  </RequesterCredentials>
					  <Item>
					    <ItemID>' . $params['item_id'] . '</ItemID><ShippingDetails>';

        //国内运输选项
        foreach ($shipping['location'] as $k => $v) {
            $requestxml.='			
			    <ShippingServiceOptions>
			        <ShippingServicePriority>' . ($k + 1) . '</ShippingServicePriority>
			        <ShippingService>' . $v['location_method'] . '</ShippingService>';
            //免运费判断
            if ($v['location_freeshipping'])
                $requestxml.='<FreeShipping>true</FreeShipping>';
            //运费			
            $requestxml.='<ShippingServiceCost>' . $v['location_cost'] . '</ShippingServiceCost>';

            //额外收费
            $requestxml.='<ShippingServiceAdditionalCost>' . $v['location_addcost'] . '</ShippingServiceAdditionalCost>';
            //如果是美国则出现高区收费
            if ($v['location_extra_cost'])
                $requestxml.='<ShippingSurcharge>' . $v['location_extra_cost'] . '</ShippingSurcharge>';

            $requestxml.='</ShippingServiceOptions>';
        }
        if (!empty($shipping['international'])) {
            $requestxml.='<ShippingType>Flat</ShippingType>';

            //国际运费选项
            foreach ((array) $shipping['international'] as $k => $v) {
                $requestxml.='<InternationalShippingServiceOption>
				        <ShippingServicePriority>' . ($k + 1) . '</ShippingServicePriority>
				        <ShippingService>' . $v['international_method'] . '</ShippingService>
				        <ShippingServiceCost>' . $v['international_cost'] . '</ShippingServiceCost>
				        <ShippingServiceAdditionalCost>' . $v['international_addcost'] . '</ShippingServiceAdditionalCost>';
                $tmp = isset($v['tolocation']) ? $v['tolocation'] : array();
                //判断是是否选的全世界配送
                if (!empty($tmp)) {
                    foreach ($tmp as $vs) {
                        $requestxml.='<ShipToLocation>' . $vs . '</ShipToLocation>';
                    }
                } else {
                    $requestxml.='<ShipToLocation>Worldwide</ShipToLocation>';
                }


                $requestxml.='</InternationalShippingServiceOption>';
            }
        }
        //运送排除
        $shipping = array_filter($shipping['exclude']);
        if (!empty($shipping)) {
            foreach ($shipping as $v) {
                $requestxml.='<ExcludeShipToLocation>' . $v . '</ExcludeShipToLocation>';
            }
        } else {
            $requestxml.='<ExcludeShipToLocation>NONE</ExcludeShipToLocation>';
        }
        $requestxml.='</ShippingDetails>';
        $requestxml.='</Item></ReviseItemRequest>';
        //file_put_contents('D:/lixing.txt',$requestxml);
//        file_put_contents(dirname(__DIR__).DIRECTORY_SEPARATOR.'logs'.DIRECTORY_SEPARATOR.'local.txt',$requestxml);
//        exit('test');
        $session = new eBaySession('', $this->devID, $this->appID, $this->certID, $this->serverUrl, $this->compatabilityLevel, $this->siteID, $verb);
        $responseXml = $session->sendHttpRequest($requestxml);
        $data = XML_unserialize($responseXml);
        return $data;
    }

    //图片设置成本地路径不变已修改的图片
    public function batchpicture2($params) {
        $verb = 'ReviseItem';
        $requestxml = '';
        $requestxml.='<?xml version="1.0" encoding="utf-8"?>
                     <ReviseItemRequest xmlns="urn:ebay:apis:eBLBaseComponents">
                     <RequesterCredentials>
                     <eBayAuthToken>' . $params['token'] . '</eBayAuthToken> 
                     </RequesterCredentials>
                     <Item>
                    <ItemID>' . $params['itemid'] . '</ItemID>
                    <PictureDetails>';
        foreach ($params['picture'] as $k => $v) {
            $requestxml.='<PictureURL>' . $v . '?' . mt_rand(100, 99999) . '</PictureURL>';
        }
        $requestxml.='</PictureDetails></Item></ReviseItemRequest>';
        $session = new eBaySession('', $this->devID, $this->appID, $this->certID, $this->serverUrl, $this->compatabilityLevel, $params['site_id'], $verb);
        $responseXml = $session->sendHttpRequest($requestxml);
        $data = XML_unserialize($responseXml);

        unset($data['ReviseItemResponse']['Fees'], $data['ReviseItemResponse attr']);
        //file_put_contents("e:/lixing1111.txt",$requestxml);	  
        return $data;
    }

    //图片设置成本地路径不变已修改的图片
    public function gengxintupian($itemid, $cover_picture, $token, $site_id) {
        $verb = 'ReviseItem';
        $requestxml = '';
        $requestxml.='<?xml version="1.0" encoding="utf-8"?>
                     <ReviseItemRequest xmlns="urn:ebay:apis:eBLBaseComponents">
                     <RequesterCredentials>
                     <eBayAuthToken>' . $token . '</eBayAuthToken> 
                     </RequesterCredentials>
                     <Item>
                    <ItemID>' . $itemid . '</ItemID>
                    <PictureDetails>';
        foreach ($cover_picture as $k => $v) {
            $requestxml.='<PictureURL>' . $v . '?' . mt_rand(100, 99999) . '</PictureURL>';
        }
        $requestxml.='</PictureDetails></Item></ReviseItemRequest>';
        $session = new eBaySession('', $this->devID, $this->appID, $this->certID, $this->serverUrl, $this->compatabilityLevel, $site_id, $verb);
        $responseXml = $session->sendHttpRequest($requestxml);
        $data = XML_unserialize($responseXml);

        unset($data['ReviseItemResponse']['Fees'], $data['ReviseItemResponse attr']);
        //file_put_contents("e:/lixing.txt",$requestxml);	  
        return $data;
    }

    public function batchpictureupdate($params) {
        $verb = 'ReviseItem';
        $requestxml = '';
        $requestxml.='<?xml version="1.0" encoding="utf-8"?>
                     <ReviseItemRequest xmlns="urn:ebay:apis:eBLBaseComponents">
                     <RequesterCredentials>
                     <eBayAuthToken>' . $params['token'] . '</eBayAuthToken> 
                     </RequesterCredentials>
                     <Item>
                    <ItemID>' . $params['itemid'] . '</ItemID>
                    <PictureDetails>
	            <PictureURL>' . $params['picture'] . '</PictureURL>';
        $requestxml.='</PictureDetails></Item></ReviseItemRequest>';
        $session = new eBaySession('', $this->devID, $this->appID, $this->certID, $this->serverUrl, $this->compatabilityLevel, $params['site_id'], $verb);
        $responseXml = $session->sendHttpRequest($requestxml);
        $data = XML_unserialize($responseXml);
        return $data;
    }

    //图片给上传，$pictureurl图片路径
    public function UploadSiteHostedPictures($pictureurl) {
        $verb = 'UploadSiteHostedPictures';
        $requestxml = '<?xml version="1.0" encoding="utf-8"?>
                     <UploadSiteHostedPicturesRequest xmlns="urn:ebay:apis:eBLBaseComponents">
                     <RequesterCredentials>
				    	 <eBayAuthToken>' . $this->token . '</eBayAuthToken> 
				     </RequesterCredentials>
				     <PictureSet>Supersize</PictureSet>
				     <ExternalPictureURL>' . $pictureurl . '</ExternalPictureURL>
				     </UploadSiteHostedPicturesRequest>';
        $session = new eBaySession('', $this->devID, $this->appID, $this->certID, $this->serverUrl, $this->compatabilityLevel, $this->siteID, $verb);
        $responseXml = $session->sendHttpRequest($requestxml);
        $data = XML_unserialize($responseXml);
        if (isset($data['UploadSiteHostedPicturesResponse']['SiteHostedPictureDetails'])) {
            return $data['UploadSiteHostedPicturesResponse']['SiteHostedPictureDetails']['PictureSetMember'][4]['MemberURL'];
        } else {
            return '';
        }
    }

    //关键字分类搜索，$category关键字
    public function GetSuggestedCategories($category) {
        $verb = 'GetSuggestedCategories';
        $requestxml = '<?xml version="1.0" encoding="utf-8"?>
				<GetSuggestedCategoriesRequest xmlns="urn:ebay:apis:eBLBaseComponents">
				 <RequesterCredentials>
				    <eBayAuthToken>' . $this->token . '</eBayAuthToken> 
				  </RequesterCredentials>
				  <Query>' . $category . '</Query>
				</GetSuggestedCategoriesRequest>';
        $session = new eBaySession('', $this->devID, $this->appID, $this->certID, $this->serverUrl, $this->compatabilityLevel, $this->siteID, $verb);
        $responseXml = $session->sendHttpRequest($requestxml);
        $data = XML_unserialize($responseXml);
        return $data;
    }

    //批量标题
    public function revisetitle($params) {
        $verb = 'ReviseItem';
        $requestxml.='<?xml version="1.0" encoding="utf-8"?>
                     <ReviseItemRequest xmlns="urn:ebay:apis:eBLBaseComponents">
                     <RequesterCredentials>
                     <eBayAuthToken>' . $this->token . '</eBayAuthToken> 
                     </RequesterCredentials>
                     <Item>
		  <Title>' . htmlspecialchars($params['title']) . '</Title>
		   <ItemID>' . $params['itemid'] . '</ItemID>
		   </Item></ReviseItemRequest>';
        $session = new eBaySession('', $this->devID, $this->appID, $this->certID, $this->serverUrl, $this->compatabilityLevel, $this->siteID, $verb);
        $responseXml = $session->sendHttpRequest($requestxml);
        $data = XML_unserialize($responseXml);
        //	return $data['GetSellerListResponse']['ItemArray']['Item'];	
        return $data;
    }

    //多进程修改标题接口
    public function updateTitle($token, $siteid, $itemid, $title) {
        $verb = 'ReviseItem';
        $requestxml = '<?xml version="1.0" encoding="utf-8"?>
					   <ReviseItemRequest xmlns="urn:ebay:apis:eBLBaseComponents">
		               <RequesterCredentials>
	                   <eBayAuthToken>' . $token . '</eBayAuthToken> 
	                    </RequesterCredentials>
	                   <Item>
	                   	  <Title>' . htmlspecialchars($title) . '</Title>
	                   		<ItemID>' . $itemid . '</ItemID>
					   </Item>
					 </ReviseItemRequest>';
        $session = new eBaySession('', $this->devID, $this->appID, $this->certID, $this->serverUrl, $this->compatabilityLevel, $siteid, $verb);
        $responseXml = $session->sendHttpRequest($requestxml);
        $data = XML_unserialize($responseXml);
        return $data;
    }

    //多进程修改标题接口
    public function updateTitlezi($token, $siteid, $itemid, $title2) {
        $verb = 'ReviseItem';
        $requestxml = '<?xml version="1.0" encoding="utf-8"?>
					   <ReviseItemRequest xmlns="urn:ebay:apis:eBLBaseComponents">
		               <RequesterCredentials>
	                   <eBayAuthToken>' . $token . '</eBayAuthToken> 
	                    </RequesterCredentials>
	                   <Item>	                   	
	                   		<ItemID>' . $itemid . '</ItemID>
                       <SubTitle>' . htmlspecialchars($title2) . '</SubTitle>
					   </Item>
					 </ReviseItemRequest>';

        $session = new eBaySession('', $this->devID, $this->appID, $this->certID, $this->serverUrl, $this->compatabilityLevel, $siteid, $verb);
        $responseXml = $session->sendHttpRequest($requestxml);
        $data = XML_unserialize($responseXml);
        return $data;
    }

    //批量付款方式
    public function Editpaypal($token, $siteid, $itemid, $paypal) {
        $verb = 'ReviseItem';
        $requestxml = '<?xml version="1.0" encoding="utf-8"?>
					   <ReviseItemRequest xmlns="urn:ebay:apis:eBLBaseComponents">
		               <RequesterCredentials>
	                   <eBayAuthToken>' . $token . '</eBayAuthToken> 
	                    </RequesterCredentials>
	                   <Item>
	                  <ItemID>' . $itemid . '</ItemID>  
					  <PaymentMethods>PayPal</PaymentMethods>
					<PayPalEmailAddress>' . $paypal . '</PayPalEmailAddress>
  					</Item>
					 </ReviseItemRequest>';
        //file_put_contents('D:/hj.txt',$requestxml);
        $session = new eBaySession('', $this->devID, $this->appID, $this->certID, $this->serverUrl, $this->compatabilityLevel, $siteid, $verb);
        $responseXml = $session->sendHttpRequest($requestxml);
        $data = XML_unserialize($responseXml);
        return $data;
    }

    //修改所在地接口
    public function locationapi($params) {
        $verb = 'ReviseItem';
        $requestxml = '<?xml version="1.0" encoding="utf-8"?>
                     <ReviseItemRequest xmlns="urn:ebay:apis:eBLBaseComponents">
                     <RequesterCredentials>
                        <eBayAuthToken>' . $this->token . '</eBayAuthToken> 
                     </RequesterCredentials>
                     <Item>
                    <ItemID>' . $params['itemid'] . '</ItemID>';
        //if($params['location_name']=='DE' || $params['location_name']=='IT' || $params['location_name']=='FR'||$params['location_name']=='ES')$requestxml.='<Country>'.$params['location_name'].'</Country>';
        if ($params['location_name'] == 'DE' || $params['location_name'] == 'IT' || $params['location_name'] == 'FR' || $params['location_name'] == 'ES')
            $requestxml.='<Country>DE</Country>';
        if (!empty($params['location_code']))
            $requestxml.='<PostalCode>' . $params['location_code'] . '</PostalCode>';
        if (!empty($params['location_item']))
            $requestxml.='<Location>' . $params['location_item'] . '</Location>';
        $requestxml.='</Item></ReviseItemRequest>';
        $session = new eBaySession('', $this->devID, $this->appID, $this->certID, $this->serverUrl, $this->compatabilityLevel, $this->siteID, $verb);
        $responseXml = $session->sendHttpRequest($requestxml);
        $data = XML_unserialize($responseXml);
        return $data;
    }

    //批量sku接口

    public function batskuapi($params) {
        $verb = 'ReviseItem';
        $requestxml = '<?xml version="1.0" encoding="utf-8"?>
                     <ReviseItemRequest xmlns="urn:ebay:apis:eBLBaseComponents">
                     <RequesterCredentials>
                        <eBayAuthToken>' . $this->token . '</eBayAuthToken> 
                     </RequesterCredentials>
                     <Item>
                    <ItemID>' . $params['itemid'] . '</ItemID>
                    <SKU>' . $params['sku'] . '</SKU>
	                  	 </Item>
	                  	 
	                  	 
	                  	 
                    </ReviseItemRequest>';
        $session = new eBaySession('', $this->devID, $this->appID, $this->certID, $this->serverUrl, $this->compatabilityLevel, $this->siteID, $verb);
        $responseXml = $session->sendHttpRequest($requestxml);
        $data = XML_unserialize($responseXml);
        return $data;
    }

    //批量系统属性和自定义属性接口
    public function itemspecificeapi($params) {
        $verb = 'ReviseItem';
        $requestxml = '<?xml version="1.0" encoding="utf-8"?>
					   <ReviseItemRequest xmlns="urn:ebay:apis:eBLBaseComponents">
		               <RequesterCredentials>
	                   <eBayAuthToken>' . $this->token . '</eBayAuthToken> 
	                    </RequesterCredentials>
	                   <Item>
	                  <ItemID>' . $params['itemid'] . '</ItemID>';
        //ebay	一级分类
        $requestxml.='<PrimaryCategory>
						<CategoryID>' . $params['listing_category1'] . '</CategoryID>
				    </PrimaryCategory>';
        //ebay 二级分类
        if ($params['listing_category2'] != 0)
            $requestxml.='
				    <SecondaryCategory>
						<CategoryID>' . $params['listing_category2'] . '</CategoryID>
				    </SecondaryCategory>';
        $requestxml.='<ItemSpecifics>';
        foreach ($params['itemvalue']['item_specifics'] as $k => $v) {

            $requestxml.='<NameValueList><Name>' . htmlspecialchars($k) . '</Name>';
            $v = str_replace('aaaaaa', '"', $v);
            $v = str_replace('bbbbbb', 'Φ', $v);
            $v = str_replace('cccccc', '℃', $v);
            $v = str_replace('dddddd', '℉', $v);
            $v = str_replace('eeeeee', '±', $v);
            $v = str_replace('ffffff', '≠', $v);
            $v = str_replace('gggggg', '≌', $v);
            $v = str_replace('hhhhhh', '≈', $v);
            $v = str_replace('iiiiii', '‰', $v);
            $v = str_replace('jjjjjj', '°', $v);
            $v = str_replace('kkkkkk', "'", $v);
            $v = str_replace('llllll', '&', $v);
            $v = str_replace('mmmmmm', ' ', $v);
            $requestxml.='<Value>' . htmlspecialchars($v) . '</Value></NameValueList>';
        }

        $requestxml.='</ItemSpecifics>';
        //UPC
        $requestxml.='<ProductListingDetails>';
        if ($this->siteID == 3) {
            $requestxml.='<EAN>Does not apply</EAN>';
        } elseif ($this->siteID == 101 || $this->siteID == 186 || $this->siteID == 71) {
            $requestxml.='<EAN>Non applicable</EAN>';
        } elseif ($this->siteID == 77) {
            $requestxml.='<EAN>Nicht zutreffend</EAN>';
        } else {
            $requestxml.='<UPC>Does not apply</UPC>';
        }
        $requestxml.='</ProductListingDetails>';
        $requestxml.='</Item></ReviseItemRequest>';
        //file_put_contents('D:/hhhhhhhhh.txt',$requestxml);
        $session = new eBaySession('', $this->devID, $this->appID, $this->certID, $this->serverUrl, $this->compatabilityLevel, $this->siteID, $verb);
        $responseXml = $session->sendHttpRequest($requestxml);
        $data = XML_unserialize($responseXml);
        return $data;
    }

    public function UploadSiteHostedPictures2222() {
        $verb = 'UploadSiteHostedPictures';
        $requestxml = '<?xml version="1.0" encoding="utf-8"?>
                     <UploadSiteHostedPicturesRequest xmlns="urn:ebay:apis:eBLBaseComponents">
                     <RequesterCredentials>
				    	 <eBayAuthToken>' . $this->token . '</eBayAuthToken> 
				     </RequesterCredentials>
				     <PictureSet>Supersize</PictureSet>
				     <ExternalPictureURL>http://www.jinlantrade.com/ebay/858a4paperc/gp208.jpg</ExternalPictureURL>
				     </UploadSiteHostedPicturesRequest>';
        $session = new eBaySession('', $this->devID, $this->appID, $this->certID, $this->serverUrl, $this->compatabilityLevel, $this->siteID, $verb);
        $responseXml = $session->sendHttpRequest($requestxml);
        $data = XML_unserialize($responseXml);
        //a($data);
        if (isset($data['UploadSiteHostedPicturesResponse']['SiteHostedPictureDetails'])) {
            return $data['UploadSiteHostedPicturesResponse']['SiteHostedPictureDetails']['PictureSetMember'][4]['MemberURL'];
        } else {
            return '';
        }
    }

    //得到item 封面的详细信息
    public function getItemCoverPicture($itemid) {
        $verb = 'GetItem';
        $requestxml = '<?xml version="1.0" encoding="utf-8"?>
					  	   <GetItemRequest xmlns="urn:ebay:apis:eBLBaseComponents">
                      	   <RequesterCredentials>
                      	   <eBayAuthToken>' . $this->token . '</eBayAuthToken> 
                      	   </RequesterCredentials>
  					  	   <ItemID>' . $itemid . '</ItemID>
  				           	<OutputSelector>Item.PictureDetails.PictureURL</OutputSelector>
							<DetailLevel>ItemReturnAttributes</DetailLevel>
                      	   </GetItemRequest>';

        $session = new eBaySession('', $this->devID, $this->appID, $this->certID, $this->serverUrl, $this->compatabilityLevel, $this->siteID, $verb);
        $responseXml = $session->sendHttpRequest($requestxml);

        $data = XML_unserialize($responseXml);
        $getdata = $data['GetItemResponse'];

        if (isset($getdata['Item']))
            return isset($getdata['Item']['PictureDetails']['PictureURL']) ? $getdata['Item']['PictureDetails']['PictureURL'] : array();
    }

    //得到item 描述的详细信息
    public function getItemDescription($itemid) {
        $verb = 'GetItem';
        $requestxml = '<?xml version="1.0" encoding="utf-8"?>
					  	   <GetItemRequest xmlns="urn:ebay:apis:eBLBaseComponents">
                      	   <RequesterCredentials>
                      	   <eBayAuthToken>' . $this->token . '</eBayAuthToken> 
                      	   </RequesterCredentials>
  					  	   <ItemID>' . $itemid . '</ItemID>
  				           	<OutputSelector>Item.Description</OutputSelector>
							<DetailLevel>ItemReturnDescription</DetailLevel>
                      	   </GetItemRequest>';

        $session = new eBaySession('', $this->devID, $this->appID, $this->certID, $this->serverUrl, $this->compatabilityLevel, $this->siteID, $verb);
        $responseXml = $session->sendHttpRequest($requestxml);

        $data = XML_unserialize($responseXml);
        $getdata = $data['GetItemResponse'];

        if (isset($getdata['Item']))
            return isset($getdata['Item']['Description']) ? $getdata['Item']['Description'] : array();
    }

    /**
     *  修改ebay分类
     */
    public function updatefenlei($params) {

        $verb = 'ReviseItem';
        $requestxml = '<?xml version="1.0" encoding="utf-8"?>
		   <ReviseItemRequest xmlns="urn:ebay:apis:eBLBaseComponents">
           <RequesterCredentials>
           <eBayAuthToken>' . $params['ebay_token'] . '</eBayAuthToken> 
            </RequesterCredentials><Item>';
        $requestxml.='<ItemID>' . $params['item_id'] . '</ItemID>';
        //ebay	一级分类
        $requestxml.='
				    <PrimaryCategory>
						<CategoryID>' . $params['listing_category1'] . '</CategoryID>
				    </PrimaryCategory>';
        //ebay 二级分类
        if ($params['listing_category2'] != 0)
            $requestxml.='
				    <SecondaryCategory>
						<CategoryID>' . $params['listing_category2'] . '</CategoryID>
				    </SecondaryCategory>';
        $requestxml.='</Item></ReviseItemRequest>';
        $session = new eBaySession('', $this->devID, $this->appID, $this->certID, $this->serverUrl, $this->compatabilityLevel, $params['site_id'], $verb);
        $responseXml = $session->sendHttpRequest($requestxml);
        $data = XML_unserialize($responseXml);
        unset($data['ReviseItemResponse attr']);
        if ($data['ReviseItemResponse']['Ack'] == 'Success')
            unset($data['ReviseItemResponse']['Fees']);
        return $data;
    }
    
    //更新多属性sku
    public function ModifySku($token, $itemid, $skus) {
        $verb = 'ReviseFixedPriceItem';
        $requestxml = '<?xml version="1.0" encoding="utf-8"?>
            <ReviseFixedPriceItemRequest xmlns="urn:ebay:apis:eBLBaseComponents">
            <RequesterCredentials>
              <eBayAuthToken>' . $token . '</eBayAuthToken>
            </RequesterCredentials>
            <ErrorLanguage>en_US</ErrorLanguage>
            <WarningLevel>High</WarningLevel>
            <Item>
              <ItemID>' . $itemid . '</ItemID>
              <Variations>';
        foreach ($skus as $sku){
            $shuxing = '';
            if($sku['attribute']){
                $temp = explode('|', $sku['attribute']);
                foreach($temp as $v){
                    if($v && ($v != '-')){
                        list($a, $b) = explode('-', $v);
                        $shuxing .= '<NameValueList>
                                    <Name>' . $a . '</Name>
                                    <Value>' . $b . '</Value>
                                  </NameValueList>';
                    }
                }
            }
            $requestxml .= '<Variation>
                <SKU>' . $sku['attribute_sku'] . '</SKU>
                <StartPrice>' . $sku['listing_price'] . '</StartPrice>
                <Quantity>' . $sku['listing_qty'] . '</Quantity>
                <VariationSpecifics>
                ' . $shuxing . '
                </VariationSpecifics>
                    <VariationProductListingDetails>
                      <UPC>Does not apply</UPC>
                      </VariationProductListingDetails>
              </Variation>';
        }
        $requestxml .= '</Variations>
            </Item>
          </ReviseFixedPriceItemRequest>';
        $session = new eBaySession('', $this->devID, $this->appID, $this->certID, $this->serverUrl, $this->compatabilityLevel, $this->siteID, $verb);
        $responseXml = $session->sendHttpRequest($requestxml);
        $data = XML_unserialize($responseXml);
        return $data;
    }
    //创建ebay广告
    public function ad_campaign($token,$data)   
    {    
        $data=json_encode($data);
        $url = 'https://api.ebay.com/sell/marketing/v1/ad_campaign';//接收地址
        $advertising= new eBayad($token,$this->appID,$this->certID,$url);       
        $advdata=$advertising->sendHttpRequest($data,1,1);
        return $advdata; 
      
    }
    //删除广告
    public function del_campaign($token,$ebay_ad_id)   
    { 
        $url = 'https://api.ebay.com/sell/marketing/v1/ad_campaign/'.trim($ebay_ad_id);//接收地址
        $deladvertising= new eBayad($token,$this->appID, $this->certID,$url);       
        $deladvdata=$deladvertising->sendHttpRequest('',1,1,1);
        return $deladvdata; 
      
    }
    //添加广告itemid
    public function ad_campaign_additem($token,$ebay_ad_id,$data)
    {

        // $data=array('bidPercentage' =>'1','listingId' =>'401248168266');
         $data=json_encode($data);
        
        //$url = 'https://api.ebay.com/sell/marketing/v1/ad_campaign';//接收地址
        //$verb='ad_campaign/10086304010/ad';
        $url='https://api.ebay.com/sell/marketing/v1/ad_campaign/'.trim($ebay_ad_id).'/ad';
        //$token='v^1.1#i^1#r^0#f^0#p^3#I^3#t^H4sIAAAAAAAAAOVXa2wURRzv9SUEKiExApXgcRUTxb2b2cfd7cqdOShNL1I4uEKkRHF2d7bdsre77KPX08TUYtCIJigSH3yASEj4ZBEkaIBEjEIEEwRFJSEKKAiaoFgTKBri7PV1rfJoy4dLvFxymZn/6/f7/2ZuBnRUjn94bf3aK1W+u0q3dICOUp8PTgDjKytm311WWl1RAgoMfFs6Hugo7yy7MMdGGc0UlmDbNHQb+9szmm4L+clYwLV0wUC2ags6ymBbcCQhnWhYINBBIJiW4RiSoQX8ydpYgEZQ4RWRo3nAAMACMqv3x2w0YgEOS8QERlmWk2kOkWXbdnFStx2kO8QdwAgFGAoyjSAq0OTLBsMRvingX4YtWzV0YhIEgXi+WiHvaxWUevNKkW1jyyFBAvFkoi69KJGsnb+wcU6oIFa8j4a0gxzXHjqaZ8jYvwxpLr55GjtvLaRdScK2HQjFezMMDSok+osZRfl5piWG4+kwYBgRgAhi7giTdYaVQc7Ny/BmVJlS8qYC1h3Vyd2KUEKG2Iolp2+0kIRI1vq9n8Uu0lRFxVYsMH9uYvnS9PwlAX86lbKMNlXGsgc0SrM8DDMcKdZySW7KbjFMU9Wb+xL1RutjeVimeYYuqx5ntn+h4czFpGo8nBtYwA0xWqQvshKK41VUYAfBAIdsk9fT3ia6TovutRVnCBH+/PDWHehXxKAG7pQm+AgDkQIhz7MQctx/acLb6yPVRdxrTSKVCmER5agMslZhx9SQhCmJMEt6YqmywLIiG0aSQjF0VKJYmZMonucwReMwDtOIYYHC/p+k4TiWKroOHpDH8IU8yFggLRkmThmaKuUCw03yp02fGNrtWKDFcUwhFMpms8EsEzSs5hANAAw90bAgLbXgDDlO+23VWxtTal4XEiZetio4OZNU005UR5LrzYE4Y8kpZDm5NNa0QUqH1hYfPnsDkLYHsrjgef42CYBMNejpOigZmZCByBb2plbmK/bfjlHIJgQFe3cFiRy0MJINXcuNxnkEPqreRkRlWLkbJ/T2+m0EGEFSJEmGqzujwdjnOgIPxdUUVdO8vTOahAXuIylTR1rOUSV7IOWYhJ8wzaRcXMKnIeIwjCgUpDGgWKzQFKJp5J3bLCNGWZEH9Jgwy7hNlfBKtchw666mjQlXQ3OxQYpCmmUjHBcBgOz1B7kxwavFbcUm1WiYZSKQD1OAZTDFihGZIlccjkJihOMZWkEix44J8zxNJcdDY67Y/pzqDdvB8tigkYthcYESMca0jCEFotEoRe6DNBXlME+JsoREniPtlCK3C3nYRMEl619X69DQl228JP+Bnb7doNP3PnkcgxCYBWvAzMqypeVlE6tt1cFBFSlBW23WyYvNwsFVOGci1Sqt9DVov7PHC97SW54EUwde0+PL4ISCpzWYPrhSASdNqYIRwEAGRGlyb20CNYOr5fDe8nt+njGreZJ66a3Tj9D7e64ewM5jZ9aBqgEjn6+ipLzTV/LS68fwYX7vC3Xdc/d9N/kd8eDmOSuuxTYnT9b8xWefmTh7/wfTrl78fsOVr6dNgduXXq5jrke3f6agskePfLP7NG/uT01+mn93hrZ+22ufr+bOLT/yxonzYMPZNRsnCXuyyq/7zv7Uc/aja5t8U57v/rtr9eStG1etu3b8+riVNfdPnJBoQokXz1xedkLeWf/Lqz++fX7XH/VPdcFD3ckN9fLj9W6ue8XR1q++XL/50uzFM+sOS+8d9UdOvbZjDdC/OHVfl9L6Q3z7c59ue7ZrR0/7zj3VPQ3Vmy7UVtRN//Z65Mjel7PGQX7qb+eOCQcOVeU+XvxQ659c24e7jl823U3jtn3y5tr41pPcIeqVi73t+wdv2Kjc5RAAAA==';
        $advertising= new eBayad($token,$this->appID,$this->certID,$url);
        $advdata=$advertising->sendHttpRequest($data,1,1);        
        //$arr = explode("\n",$advdata);
        return $advdata;
        //$ebay_ad_id=basename($arr[3]);
        
    }
     //删除广告itemid
    public function ad_campaign_delitem($token,$ebay_ad_id,$data)
    {
        //$data=array('requests' =>array('listingId' =>'401292548688'));
        $data=json_encode($data);
        //$data='{"requests":[{"listingId":"401292548688"}]}';
        //$url = 'https://api.ebay.com/sell/marketing/v1/ad_campaign';//接收地址
        $url='https://api.ebay.com/sell/marketing/v1/ad_campaign/'.trim($ebay_ad_id).'/bulk_delete_ads_by_listing_id';
        $advertising= new eBayad($token,$this->appID,$this->certID,$url);
        $advdata=$advertising->sendHttpRequest($data,1);
        return $advdata;
       // $arr = explode("\n",$advdata);
        //$ebay_ad_id=basename($arr[3]);
        
    }
    //更新广告
    public function up_campaign($token,$ebay_ad_id,$data)
    {
             //$data='{"requests":[{"listingId":"401292548688"}]}';
        //$url = 'https://api.ebay.com/sell/marketing/v1/ad_campaign';//接收地址
        $url='https://api.ebay.com/sell/marketing/v1/ad_campaign/'.trim($ebay_ad_id).'/update_campaign_identification';
        $advertising= new eBayad($token,$this->appID,$this->certID,$url);
        $upadvdata=$advertising->sendHttpRequest($data,1,1);
        a($upadvdata);
        return $upadvdata;
    }
    //验证广告是否存在
    
    public function ad_token()
    {
        
        $url = 'https://api.ebay.com/identity/v1/oauth2/token';
        $advertising= new eBayad('',$this->appID, $this->certID,$url);
        $advdata=$advertising->sendHttpRequest($verb,$data);
        
        
        
        try
            {
        	// $a = 'v%5E1.1%23i%5E1%23p%5E3%23f%5E0%23r%5E1%23I%5E3%23t%5EUl41XzM6NUE3QkQ5OUUyMEYzRTdCNzI3MTUwQzFGOTUzOUFBNzJfMV8xI0VeMjYw';
        	// $a = urldecode($a);
        	// print_r($a);
        	// exit;
        	$url = 'https://api.ebay.com/identity/v1/oauth2/token';
        	$header = [
        		'Content-type: application/x-www-form-urlencoded', 
        		'Authorization: Basic '.base64_encode($this->appID.':'.$this->certID),
        	];
        	// $data = [
        		// 'grant_type' => 'authorization_code',
        		// 'code' => 'v%5E1.1%23i%5E1%23p%5E3%23f%5E0%23r%5E1%23I%5E3%23t%5EUl41XzM6NUE3QkQ5OUUyMEYzRTdCNzI3MTUwQzFGOTUzOUFBNzJfMV8xI0VeMjYw',
        		// 'redirect_uri' => '-21a5e17f-12e0-4-futkfg',
        	// ];
            $data='grant_type=authorization_code&code=v^1.1%23i^1%23I^3%23r^1%23p^3%23f^0%23t^Ul41XzY6RDRDNTg4OTQwMDhERkE0OTlBMEJEMEVBNTI0MEE0M0JfMl8xI0VeMjYw&redirect_uri='.$this->runame;
        	$time = 30;
        	$ch = curl_init();
        	curl_setopt($ch, CURLOPT_URL,$url); 
        	curl_setopt($ch, CURLOPT_HEADER, false);
        	curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        	
        	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        	curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        		
        	curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
        	curl_setopt($ch, CURLOPT_POST,1); 
        	// curl_setopt($ch, CURLOPT_POSTFIELDS, $data); 
        	// curl_setopt($ch, CURLOPT_POSTFIELDS, 'grant_type='.urlencode('authorization_code').'&code='.urlencode('v^1.1%23i^1%23I^3%23r^1%23p^3%23f^0%23t^Ul41XzY6RDRDNTg4OTQwMDhERkE0OTlBMEJEMEVBNTI0MEE0M0JfMl8xI0VeMjYw').'&redirect_uri='.urlencode('-21a5e17f-12e0-4-futkfg'));
        	curl_setopt($ch, CURLOPT_POSTFIELDS, 'grant_type='.urlencode('authorization_code').'&code=v^1.1%23i^1%23I^3%23r^1%23p^3%23f^0%23t^Ul41XzY6RDRDNTg4OTQwMDhERkE0OTlBMEJEMEVBNTI0MEE0M0JfMl8xI0VeMjYw&redirect_uri='.urlencode('-21a5e17f-12e0-4-futkfg'));
        	// curl_setopt($ch, CURLOPT_POSTFIELDS, 'grant_type=authorization_code&code=v^1.1#i^1#p^3#f^0#r^1#I^3#t^Ul41XzM6NUE3QkQ5OUUyMEYzRTdCNzI3MTUwQzFGOTUzOUFBNzJfMV8xI0VeMjYw&redirect_uri=-21a5e17f-12e0-4-futkfg');
        	curl_setopt($ch, CURLOPT_TIMEOUT, $time);
        	$info   = curl_exec($ch);
        	curl_close($ch);
        	print_r($info);
        	
        	
        }
        catch(\Exception $ex)
        {
        	echo  "ERROR:".$ex->getMessage() 
        	."  <br>LINE:".$ex->getLine()
        	."  <br>FILE:".$ex->getFile()
        	."  <br>CODE:".$ex->getCode();
        			
        }
            
    }
  //更新用户token
 public function up_token($refresh_token)
 {
    $url = 'https://api.ebay.com/identity/v1/oauth2/token';
    $data='grant_type=refresh_token&refresh_token='.$refresh_token;
    $ad_uptoken= new eBayad('',$this->appID, $this->certID,$url);
    $advdata=$ad_uptoken->sendHttpRequest($data);
    return $advdata;
    //$advdata=json_decode($advdata,true);
    //a($advdata);
  }

}

?>