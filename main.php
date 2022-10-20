<?php

// Initialize collection plan
require_once(APPROOT.'collectors/src/AzureCollectionPlan.class.inc.php');
$oAzureCollectionPlan = new AzureCollectionPlan();
$oAzureCollectionPlan->Init();
$oAzureCollectionPlan->AddCollectorsToOrchestrator();
