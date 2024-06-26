<?php
require_once 'vendor/autoload.php';

use MetarDecoder\MetarDecoder;

require __DIR__ . '/airports.php';

if (!isset($_GET['metar'])) {
    echo "'metar' parameter is required";
    http_response_code(400);
    die();
}

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

if ($decoded->isValid() == false) {
    exit('Invalid METAR.');
}

// Airport, date & time
$airportName = (isset($airports[$decoded->getIcao()])) ? $airports[$decoded->getIcao()] : $decoded->getIcao();

if ($type === 'D') {
    print($airportName . ' Departure Information ');
} elseif ($type === 'A') {
    print($airportName . ' Arrival Information ');
} else {
    print($airportName . ' Information ');
}
print($_GET['info'] . ', '. substr($rawMetar, 7, 4) . ' [UTC]');
if (strpos($decoded->getTime(), ':00') === false and strpos($decoded->getTime(), ':30') === false) {
    print(', special');
}
print(', ');

// Operational Runway
if ($type === 'D') {
    print('Departure runway ' . $_GET['dep'] . ', ');
} elseif ($type === 'A') {
    print('Landing runway ' . $_GET['arr'] . ', ' . $_GET['apptype'] . ' approach expected, ');
} else {
    if ($_GET['dep'] === $_GET['arr']) {
        print('Runway ' . $_GET['dep']);
    } else {
        print('Departure runway ' . $_GET['dep'] . ', landing runway ' . $_GET['arr']);
    }
    print(', ' . $_GET['apptype'] . ' approach expected, ');
}

// Wind
print('Wind ');
if ($surfaceWindObj->getMeanSpeed()->getValue() == 0) {
    print('calm');
} else {
    if ($surfaceWindObj->withVariableDirection() == true) {
        print('variable');
    } else {
        if ($surfaceWindObj->getMeanDirection()->getValue() < 100) {
            print('0');
        }
        print($surfaceWindObj->getMeanDirection()->getValue() . ' degrees');
    }
    print(' at ');
    print($surfaceWindObj->getMeanSpeed()->getValue() . ' [' . $surfaceWindObj->getMeanSpeed()->getUnit() . ']');
}
if ($surfaceWindObj->getSpeedVariations() != null) {
    print(', gusting to ' . $surfaceWindObj->getSpeedVariations()->getValue() . ' ' . $surfaceWindObj->getMeanSpeed()->getUnit());
}
if ($surfaceWindObj->getDirectionVariations() != null) {
    print(', variable between ');
    if ($surfaceWindObj->getDirectionVariations()[0]->getValue() < 100) {
        print('0');
    }
    print($surfaceWindObj->getDirectionVariations()[0]->getValue() . ' and ');
    if ($surfaceWindObj->getDirectionVariations()[1]->getValue() < 100) {
        print('0');
    }
    print($surfaceWindObj->getDirectionVariations()[1]->getValue() . ' degrees');
}
print(', ');

// Visibility & Special Weather
if (strpos($rawMetar, 'CAVOK') !== false) {
    print('[CAVOK]');
} else {
    // Visibility
    print('Visibility ');
    if ($visObj->getVisibility()->getValue() == 9999) {
        print('greater than [10] kilometers');
    } else {
        print('{' . $visObj->getVisibility()->getValue() . '}');
        switch ($visObj->getVisibility()->getUnit()) {
        case 'm':
            print(' meter');
            break;
        case 'SM':
            print(' mile');
            break;
        }
        if ($visObj->getVisibility()->getValue() != 1) {
            print('s');
        }
    }
    print(', ');

    // RVR
    if ($rvr != null) {
        foreach ($rvr as $runwayRvr) {
            print('Runway ' . $runwayRvr->getRunway() . ' RVR, ');
            if ($runwayRvr->getVisualRange() == null) {
                print(' variable between [' . $runwayRvr->getVisualRangeInterval()[0]->getValue() . '] and [' . $runwayRvr->getVisualRangeInterval()[1]->getValue() . ']');
                if ($runwayRvr->getVisualRangeInterval()[0]->getUnit() == 'ft') {
                    print(' feet');
                } elseif ($runwayRvr->getVisualRangeInterval()[0]->getUnit() == 'm') {
                    print(' meter');
                    if ($runwayRvr->getVisualRangeInterval()[0]->getValue() != 1) {
                        print('s');
                    }
                }
            } else {
                print('{' . $runwayRvr->getVisualRange()->getValue() . '}');
                if ($runwayRvr->getVisualRange()->getUnit() == 'ft') {
                    print(' feet');
                } elseif ($runwayRvr->getVisualRange()->getUnit() == 'm') {
                    print(' meter');
                    if ($runwayRvr->getVisualRange()->getValue() != 1) {
                        print('s');
                    }
                }
            }
            switch ($runwayRvr->getPastTendency()) {
            case 'D':
                print(' downward');
                break;
            case 'N':
                break;
            case 'U':
                print(' upward');
                break;
            }
            print(', ');
        }
    }

    // Cloud & Weather Phenomenon
    if (strpos($rawMetar, 'NSC')) {
        print('No significant clouds, ');
    }

    if ($phenomenon) {
        foreach ($phenomenon as $index => $pwn) {
            if ($index >= 1) {
                print(', ');
            }
            if ((string) $pwn->getIntensityProximity() !== '') {
                switch ((string) $pwn->getIntensityProximity()) {
                case '+':
                    print('Heavy');
                    break;
                case '-':
                    print('Light');
                    break;
                }
            }
            if ($pwn->getCharacteristics() !== '') {
                switch ($pwn->getCharacteristics()) {
                case 'MI':
                    print(' shallow');
                    break;
                case 'BC':
                    print(' patches');
                    break;
                case 'PR':
                    print(' partial');
                    break;
                case 'DR':
                    print(' drifting');
                    break;
                case 'BL':
                    print(' blowing');
                    break;
                case 'SH':
                    print(' showers');
                    break;
                case 'TS':
                    print(' thunderstorm');
                    break;
                case 'FZ':
                    print(' freezing');
                    break;
                }
            }
            if ($pwn->getTypes()) {
                foreach ($pwn->getTypes() as $pwntype) {
                    switch ($pwntype) {
                    case 'DZ':
                        print(' drizzle');
                        break;
                    case 'RA':
                        print(' rain');
                        break;
                    case 'SN':
                        print(' snow');
                        break;
                    case 'SG':
                        print(' snow grains');
                        break;
                    case 'IC':
                        print(' ice crystals');
                        break;
                    case 'PL':
                        print(' ice pellets');
                        break;
                    case 'GR':
                        print(' hail');
                        break;
                    case 'GS':
                        print(' snow pellets');
                        break;
                    case 'UP':
                        print(' unknown precipitation');
                        break;
                    case 'BR':
                        print(' mist');
                        break;
                    case 'FG':
                        print(' fog');
                        break;
                    case 'FU':
                        print(' smoke');
                        break;
                    case 'VA':
                        print(' volcanic ash');
                        break;
                    case 'DU':
                        print(' dust');
                        break;
                    case 'SA':
                        print(' sand');
                        break;
                    case 'HZ':
                        print(' haze');
                        break;
                    case 'PO':
                        print(' dust whirls');
                        break;
                    case 'SQ':
                        print(' squalls');
                        break;
                    case 'FC':
                        print(' funnel cloud');
                        break;
                    case 'SS':
                        print(' sandstorm');
                        break;
                    case 'DS':
                        print(' duststorm');
                        break;
                    }
                }
            }
            if ((string) $pwn->getIntensityProximity() == 'VC') {
                print(' in the vicinity');
            }
        }
        print(', ');
    }
}
if (strpos($rawMetar, 'CLR') or strpos($rawMetar, 'SKC')) {
    print('Sky clear, ');
}

// Cloud
if ($clouds) {
    foreach ($clouds as $index => $cloud) {
        if ($index >= 1) {
            print(', ');
        }
        switch ($cloud->getAmount()) {
        case 'FEW':
            print('Few');
            break;
        case 'SCT':
            print('Scattered');
            break;
        case 'BKN':
            print('Broken');
            break;
        case 'OVC':
            print('Overcast');
            break;
        case 'VV':
            print('Vertical visibility');
            break;
        }
        switch ($cloud->getBaseHeight()->getUnit()) {
        case 'ft';
            print(' [' . $cloud->getBaseHeight()->getValue() * 0.3 . ']' . ' meters');
        }
        switch ($cloud->getType()) {
        case 'CB':
            print(' cumulonimbus');
            break;
        case 'TCU':
            print(' towering cumulus');
            break;
        }
    }
    print(', ');
}


// Miscellaneous
print('Temperature ' . $decoded->getAirTemperature()->getValue() .
    ' [' . $decoded->getAirTemperature()->getUnit() . '], dewpoint ' . $decoded->getDewPointTemperature()->getValue() .
    ' [' . $decoded->getDewPointTemperature()->getUnit() . '], QNH ' .
    $decoded->getPressure()->getValue() . ' [' . $decoded->getPressure()->getUnit() . '], ');

// Wind Shear Alert
if ($decoded->getWindshearAllRunways()) {
    print('Wind shear, all runways. ');
} else if ($windShearAlerts) {
    print('Wind shear, runway ');
    foreach ($windShearAlerts as $index => $runway) {
        if ($index >= 1) {
            print(', ');
        }
        print($runway);
    }
    print(', ');
}

print('Advise on initial contact you have information ' . $_GET['info'] . ', and confirm you will implement [RNAV] procedures.');
