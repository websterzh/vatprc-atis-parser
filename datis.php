<?php
require_once 'vendor/autoload.php';

use MetarDecoder\MetarDecoder;

require '/airports.php';

$rawMetar = $_GET['metar'];
// FIXME: Dirty fix to issue caused by `8000NW` in the METAR
// METAR ZMUB 100530Z VRB02MPS 8000NW BKN250 M07/M12 Q1005 NOSIG RMK QFE647.5 62 NW MO=
$rawMetar = preg_replace('/ (\d{4})[NWSE]+ /', ' $1 ', $rawMetar);

$decoder = new MetarDecoder();
$decoded = $decoder->parse($rawMetar);
$surfaceWindObj = $decoded->getSurfaceWind(); //SurfaceWind object
$visObj = $decoded->getVisibility(); //Visibility object
$rvr = $decoded->getRunwaysVisualRange(); //RunwayVisualRange array
$phenomenon = $decoded->getPresentWeather(); //WeatherPhenomenon array
$clouds = $decoded->getClouds(); //CloudLayer array
$windShearAlerts = $decoded->getWindshearRunways();
$type = $_GET['type'] ?? null;

function reform($num) //,替换成AND
{
    $org = array(",");
    $after = array(" AND ");
    print str_replace($org, $after, $num);
}

if ($decoded->isValid() == false) {
    exit('Invalid METAR.');
}

// Airport, date & time
print($decoded->getIcao() . ' ');
if ($type === 'D') {
    print('DEP ATIS ');
} elseif ($type === 'A') {
    print('ARR ATIS ');
} else {
    print('ATIS ');
}
print($_GET['info'] . ' '. substr($rawMetar, 7, 4) . 'Z ');

// Operational Runway
if ($type === 'D') {
    print reform('DEP RWY '  . $_GET['dep']);
} elseif ($type === 'A') {
    print reform('EXP ' . $_GET ['apptype'] . ' ARR RWY ' . $_GET['arr']);
} else {
    print reform('DEP RWY '  . $_GET['dep'] . ' EXP ' . $_GET ['apptype'] . ' ARR RWY ' . $_GET['arr']);
}

// Wind Shear Alert
if ($decoded->getWindshearAllRunways()) {
    print(' CTN WS ALL RWYS ');
} else if ($windShearAlerts) {
    print(' CTN WS RWY ');
    foreach ($windShearAlerts as $index => $runway) {
        if ($index >= 1) {
            print('AND ');
        }
        print($runway);
    }
    print(' ');
}

//NOTAM (reserve)


// Visibility
if (strpos($rawMetar, 'CAVOK') !== false) {    
    print(' CAVOK ');
} else {
    print(' VIS ' . $visObj->getVisibility()->getValue() . ' M ');
}

// RVR
if ($rvr != null) {
    foreach ($rvr as $runwayRvr) {
        print('RVR RWY ');
        print($runwayRvr->getRunway());
        if ($runwayRvr->getVisualRange() == null) {
            print(' BTW ');
            print($runwayRvr->getVisualRangeInterval()[0]->getValue());
            print(' M AND ');
            print($runwayRvr->getVisualRangeInterval()[1]->getValue());
            print(' M ');
        } else {
            print(' ');
            print($runwayRvr->getVisualRange()->getValue());
            print(' M');
        }
        switch ($runwayRvr->getPastTendency()) {
        case 'D':
                print(' DOWNWARD TNDCY ');
            break;
        case 'N':
                print(' NC ');
            break;
        case 'U':
                print(' UPWARD TNDCY ');
            break;
        }
    }
}

// Cloud & Weather Phenomenon
if (strpos($rawMetar, 'NSC') === true) {
    print(' NSC');
}
foreach ($phenomenon as $pwn) {
    if ((string)$pwn->getIntensityProximity() !== '') {
        print($pwn->getIntensityProximity());
    }
    if ($pwn->getCharacteristics() !== '') {
        print($pwn->getCharacteristics());
    }
    if (is_array($pwn->getTypes())) {
        foreach ($pwn->getTypes() as $pwntype) {
            print($pwntype);
            print(' ');
        }
    } else {
        print(' ');
    }
}
foreach ($clouds as $cloud) {
    print($cloud->getAmount());
    $baseHeight = $cloud->getBaseHeight();
    if ($baseHeight !== null) {
        print(' ' . $cloud->getBaseHeight()->getValue() * 0.3);
    } else {
        print(' 0');
    }
    print('M');
    print($cloud->getType());
    print(' ');
}

// Wind
print(' WIND ');

if ($surfaceWindObj->getMeanSpeed()->getValue() == 0) {
    print('CALM ');
} else {
    if ($surfaceWindObj->withVariableDirection() == true) {
        print('VRB DEG ');
    } else {
        if ($surfaceWindObj->getMeanDirection()->getValue() < 100) {
            print('0');
        }
        print($surfaceWindObj->getMeanDirection()->getValue() . ' DEG ');
    }
    
    $raw_sw = $surfaceWindObj->getMeanSpeed()->getValue();
    $int_sw = (int)$raw_sw;
    $str_sw = strval($int_sw);
    if ($int_sw < 10) {
        $out_sw = '0' . $str_sw ;
    } else {
        $out_sw = $str_sw ;
    }

    print($out_sw . ' MPS ');

}
If ($surfaceWindObj->getSpeedVariations() != null) {
    if ($surfaceWindObj->getSpeedVariations()->getValue() < 10) {
        print(' GUST 0' . $surfaceWindObj->getSpeedVariations()->getValue() . ' MPS ');
    } else {
            print(' GUST ' . $surfaceWindObj->getSpeedVariations()->getValue() . ' MPS ');
    }
}
if ($surfaceWindObj->getDirectionVariations() != null) {
    if ($surfaceWindObj->getDirectionVariations()[0]->getValue() < 100) {
        print('0');
    }
    print($surfaceWindObj->getDirectionVariations()[0]->getValue() . 'V');
    if ($surfaceWindObj->getDirectionVariations()[1]->getValue() < 100) {
        print('0');
    }
    print($surfaceWindObj->getDirectionVariations()[1]->getValue() . ' DEG ');
}

// Miscellaneous
$temp_data = $decoded->getAirTemperature()->getValue();
$int_temp_data = (int)$temp_data;
$str_temp_data = strval($int_temp_data);
if ($int_temp_data < 10 && $int_temp_data > 0) {
    $out_temp_data = '0' . $str_temp_data ;
} elseif ($int_temp_data < 0 && $int_temp_data > -10) {
    $out_temp_data = '-0' . $str_temp_data[1];
} elseif ($int_temp_data == 0) {
    $out_temp_data = '00';
} else {
    $out_temp_data = $str_temp_data ;
}

$dewpt_data = $decoded->getDewPointTemperature()->getValue();
$int_dewpt_data = (int)$dewpt_data;
$str_dewpt_data = strval($int_dewpt_data);
if ($int_dewpt_data < 10 && $int_dewpt_data > 0) {
        $out_dewpt_data = '0' . $str_dewpt_data ;
} elseif ($int_dewpt_data < 0 && $int_dewpt_data > -10) {
        $out_dewpt_data = '-0' . $str_dewpt_data[1];
} elseif ($int_dewpt_data == 0) {
        $out_dewpt_data = '00';
} else {
        $out_dewpt_data = $str_dewpt_data ;
}

print(' T ' . $out_temp_data . ' /DP ' . $out_dewpt_data . ' QNH ' . $decoded->getPressure()->getValue() . ' HPA ');

print ('RPT RECEIPT OF ATIS ' . $_GET['info'] . ' ON ' . $decoded->getIcao());

?>