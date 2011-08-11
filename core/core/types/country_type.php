<?php namespace melt\core;

/**
 * CountryType stores countries using the ISO 3166-1 encoding.
 */
class CountryType extends \melt\AppType {
    public function getSQLType() {
        return "varchar(2)";
    }

    public function getSQLValue() {
        return \melt\db\strfy($this->value);
    }

    public function getInterface($name) {
        $countries_list = $this->getCountryList();
        $value = strval($this->value);
        $html = "<select id=\"$name\" name=\"$name\">";
        $nothing = __("No Country Selected");
        $html .= "<option style=\"font-style: italic;\" value=\"\">$nothing</option>";
        $selected = ' selected="selected"';
        foreach ($countries_list as $country_id => $country) {
            $s = ($value == $country_id)? $selected: null;
            $country_name = escape($country[2]);
            $html .= "<option$s value=\"$country_id\">$country_name</option>";
        }
        $html .= "</select>";
        return $html;
    }

    public function readInterface($name) {
        $countries_list = $this->getCountryList();
        $value = @$_POST[$name];
        if (isset($countries_list[$value]))
            $this->value = $value;
        else
            $this->value = null;
    }
    
    public function __toString() {
        return (string)$this->getCountryName();
    }

    public function getCountryName() {
        $countries_list = $this->getCountryList();
        if (isset($countries_list[$this->value]))
            return $countries_list[$this->value][2];
        else
            return null;
    }
    
    public function setCountryName($country_name, $is_translated = false) {
        $country_name = trime($country_name);
        foreach ($this->getCountryList() as $a2c => $country_data) {
            list(,, $this_translated_country, $this_english_country) = $country_data;
             $this_country = $is_translated? $this_translated_country: $this_english_country;
             if (strcasecmp($this_country, $country_name) !== 0)
                continue;
             $this->set($a2c);
             return true;
        }
        return false;
    }

    public function getAsAlpha2Code() {
        $countries_list = $this->getCountryList();
        if (isset($countries_list[$this->value]))
            return $this->value;
        else
            return null;
    }

    public function getAsAlpha3Code() {
        $countries_list = $this->getCountryList();
        if (isset($countries_list[$this->value]))
            return $countries_list[$this->value][0];
        else
            return null;
    }

    public function getAsNumericCode() {
        $countries_list = $this->getCountryList();
        if (isset($countries_list[$this->value]))
            return $countries_list[$this->value][1];
        else
            return null;
    }

    protected static function getCountryList() {
        static $country_names = null;
        if ($country_names === null) {
            $country_names = array(
                "AF" => array("AFG", 4, _("Afghanistan"), "Afghanistan"),
                "AX" => array("ALA", 248, _("Åland Islands"), "Åland Islands"),
                "AL" => array("ALB", 8, _("Albania"), "Albania"),
                "DZ" => array("DZA", 12, _("Algeria"), "Algeria"),
                "AS" => array("ASM", 16, _("American Samoa"), "American Samoa"),
                "AD" => array("AND", 20, _("Andorra"), "Andorra"),
                "AO" => array("AGO", 24, _("Angola"), "Angola"),
                "AI" => array("AIA", 660, _("Anguilla"), "Anguilla"),
                "AQ" => array("ATA", 10, _("Antarctica"), "Antarctica"),
                "AG" => array("ATG", 28, _("Antigua and Barbuda"), "Antigua and Barbuda"),
                "AR" => array("ARG", 32, _("Argentina"), "Argentina"),
                "AM" => array("ARM", 51, _("Armenia"), "Armenia"),
                "AW" => array("ABW", 533, _("Aruba"), "Aruba"),
                "AU" => array("AUS", 36, _("Australia"), "Australia"),
                "AT" => array("AUT", 40, _("Austria"), "Austria"),
                "AZ" => array("AZE", 31, _("Azerbaijan"), "Azerbaijan"),
                "BS" => array("BHS", 44, _("Bahamas"), "Bahamas"),
                "BH" => array("BHR", 48, _("Bahrain"), "Bahrain"),
                "BD" => array("BGD", 50, _("Bangladesh"), "Bangladesh"),
                "BB" => array("BRB", 52, _("Barbados"), "Barbados"),
                "BY" => array("BLR", 112, _("Belarus"), "Belarus"),
                "BE" => array("BEL", 56, _("Belgium"), "Belgium"),
                "BZ" => array("BLZ", 84, _("Belize"), "Belize"),
                "BJ" => array("BEN", 204, _("Benin"), "Benin"),
                "BM" => array("BMU", 60, _("Bermuda"), "Bermuda"),
                "BT" => array("BTN", 64, _("Bhutan"), "Bhutan"),
                "BO" => array("BOL", 68, _("Bolivia, Plurinational State of"), "Bolivia, Plurinational State of"),
                "BA" => array("BIH", 70, _("Bosnia and Herzegovina"), "Bosnia and Herzegovina"),
                "BW" => array("BWA", 72, _("Botswana"), "Botswana"),
                "BV" => array("BVT", 74, _("Bouvet Island"), "Bouvet Island"),
                "BR" => array("BRA", 76, _("Brazil"), "Brazil"),
                "IO" => array("IOT", 86, _("British Indian Ocean Territory"), "British Indian Ocean Territory"),
                "BN" => array("BRN", 96, _("Brunei Darussalam"), "Brunei Darussalam"),
                "BG" => array("BGR", 100, _("Bulgaria"), "Bulgaria"),
                "BF" => array("BFA", 854, _("Burkina Faso"), "Burkina Faso"),
                "BI" => array("BDI", 108, _("Burundi"), "Burundi"),
                "KH" => array("KHM", 116, _("Cambodia"), "Cambodia"),
                "CM" => array("CMR", 120, _("Cameroon"), "Cameroon"),
                "CA" => array("CAN", 124, _("Canada"), "Canada"),
                "CV" => array("CPV", 132, _("Cape Verde"), "Cape Verde"),
                "KY" => array("CYM", 136, _("Cayman Islands"), "Cayman Islands"),
                "CF" => array("CAF", 140, _("Central African Republic"), "Central African Republic"),
                "TD" => array("TCD", 148, _("Chad"), "Chad"),
                "CL" => array("CHL", 152, _("Chile"), "Chile"),
                "CN" => array("CHN", 156, _("China"), "China"),
                "CX" => array("CXR", 162, _("Christmas Island"), "Christmas Island"),
                "CC" => array("CCK", 166, _("Cocos (Keeling) Islands"), "Cocos (Keeling) Islands"),
                "CO" => array("COL", 170, _("Colombia"), "Colombia"),
                "KM" => array("COM", 174, _("Comoros"), "Comoros"),
                "CG" => array("COG", 178, _("Congo"), "Congo"),
                "CD" => array("COD", 180, _("Congo, the Democratic Republic of the"), "Congo, the Democratic Republic of the"),
                "CK" => array("COK", 184, _("Cook Islands"), "Cook Islands"),
                "CR" => array("CRI", 188, _("Costa Rica"), "Costa Rica"),
                "CI" => array("CIV", 384, _("Côte d'Ivoire"), "Côte d'Ivoire"),
                "HR" => array("HRV", 191, _("Croatia"), "Croatia"),
                "CU" => array("CUB", 192, _("Cuba"), "Cuba"),
                "CY" => array("CYP", 196, _("Cyprus"), "Cyprus"),
                "CZ" => array("CZE", 203, _("Czech Republic"), "Czech Republic"),
                "DK" => array("DNK", 208, _("Denmark"), "Denmark"),
                "DJ" => array("DJI", 262, _("Djibouti"), "Djibouti"),
                "DM" => array("DMA", 212, _("Dominica"), "Dominica"),
                "DO" => array("DOM", 214, _("Dominican Republic"), "Dominican Republic"),
                "EC" => array("ECU", 218, _("Ecuador"), "Ecuador"),
                "EG" => array("EGY", 818, _("Egypt"), "Egypt"),
                "SV" => array("SLV", 222, _("El Salvador"), "El Salvador"),
                "GQ" => array("GNQ", 226, _("Equatorial Guinea"), "Equatorial Guinea"),
                "ER" => array("ERI", 232, _("Eritrea"), "Eritrea"),
                "EE" => array("EST", 233, _("Estonia"), "Estonia"),
                "ET" => array("ETH", 231, _("Ethiopia"), "Ethiopia"),
                "FK" => array("FLK", 238, _("Falkland Islands (Malvinas)"), "Falkland Islands (Malvinas)"),
                "FO" => array("FRO", 234, _("Faroe Islands"), "Faroe Islands"),
                "FJ" => array("FJI", 242, _("Fiji"), "Fiji"),
                "FI" => array("FIN", 246, _("Finland"), "Finland"),
                "FR" => array("FRA", 250, _("France"), "France"),
                "GF" => array("GUF", 254, _("French Guiana"), "French Guiana"),
                "PF" => array("PYF", 258, _("French Polynesia"), "French Polynesia"),
                "TF" => array("ATF", 260, _("French Southern Territories"), "French Southern Territories"),
                "GA" => array("GAB", 266, _("Gabon"), "Gabon"),
                "GM" => array("GMB", 270, _("Gambia"), "Gambia"),
                "GE" => array("GEO", 268, _("Georgia"), "Georgia"),
                "DE" => array("DEU", 276, _("Germany"), "Germany"),
                "GH" => array("GHA", 288, _("Ghana"), "Ghana"),
                "GI" => array("GIB", 292, _("Gibraltar"), "Gibraltar"),
                "GR" => array("GRC", 300, _("Greece"), "Greece"),
                "GL" => array("GRL", 304, _("Greenland"), "Greenland"),
                "GD" => array("GRD", 308, _("Grenada"), "Grenada"),
                "GP" => array("GLP", 312, _("Guadeloupe"), "Guadeloupe"),
                "GU" => array("GUM", 316, _("Guam"), "Guam"),
                "GT" => array("GTM", 320, _("Guatemala"), "Guatemala"),
                "GG" => array("GGY", 831, _("Guernsey"), "Guernsey"),
                "GN" => array("GIN", 324, _("Guinea"), "Guinea"),
                "GW" => array("GNB", 624, _("Guinea-Bissau"), "Guinea-Bissau"),
                "GY" => array("GUY", 328, _("Guyana"), "Guyana"),
                "HT" => array("HTI", 332, _("Haiti"), "Haiti"),
                "HM" => array("HMD", 334, _("Heard Island and McDonald Islands"), "Heard Island and McDonald Islands"),
                "VA" => array("VAT", 336, _("Holy See (Vatican City State)"), "Holy See (Vatican City State)"),
                "HN" => array("HND", 340, _("Honduras"), "Honduras"),
                "HK" => array("HKG", 344, _("Hong Kong"), "Hong Kong"),
                "HU" => array("HUN", 348, _("Hungary"), "Hungary"),
                "IS" => array("ISL", 352, _("Iceland"), "Iceland"),
                "IN" => array("IND", 356, _("India"), "India"),
                "ID" => array("IDN", 360, _("Indonesia"), "Indonesia"),
                "IR" => array("IRN", 364, _("Iran, Islamic Republic of"), "Iran, Islamic Republic of"),
                "IQ" => array("IRQ", 368, _("Iraq"), "Iraq"),
                "IE" => array("IRL", 372, _("Ireland"), "Ireland"),
                "IM" => array("IMN", 833, _("Isle of Man"), "Isle of Man"),
                "IL" => array("ISR", 376, _("Israel"), "Israel"),
                "IT" => array("ITA", 380, _("Italy"), "Italy"),
                "JM" => array("JAM", 388, _("Jamaica"), "Jamaica"),
                "JP" => array("JPN", 392, _("Japan"), "Japan"),
                "JE" => array("JEY", 832, _("Jersey"), "Jersey"),
                "JO" => array("JOR", 400, _("Jordan"), "Jordan"),
                "KZ" => array("KAZ", 398, _("Kazakhstan"), "Kazakhstan"),
                "KE" => array("KEN", 404, _("Kenya"), "Kenya"),
                "KI" => array("KIR", 296, _("Kiribati"), "Kiribati"),
                "KP" => array("PRK", 408, _("Korea, Democratic People's Republic of"), "Korea, Democratic People's Republic of"),
                "KR" => array("KOR", 410, _("Korea, Republic of"), "Korea, Republic of"),
                "KW" => array("KWT", 414, _("Kuwait"), "Kuwait"),
                "KG" => array("KGZ", 417, _("Kyrgyzstan"), "Kyrgyzstan"),
                "LA" => array("LAO", 418, _("Lao People's Democratic Republic"), "Lao People's Democratic Republic"),
                "LV" => array("LVA", 428, _("Latvia"), "Latvia"),
                "LB" => array("LBN", 422, _("Lebanon"), "Lebanon"),
                "LS" => array("LSO", 426, _("Lesotho"), "Lesotho"),
                "LR" => array("LBR", 430, _("Liberia"), "Liberia"),
                "LY" => array("LBY", 434, _("Libyan Arab Jamahiriya"), "Libyan Arab Jamahiriya"),
                "LI" => array("LIE", 438, _("Liechtenstein"), "Liechtenstein"),
                "LT" => array("LTU", 440, _("Lithuania"), "Lithuania"),
                "LU" => array("LUX", 442, _("Luxembourg"), "Luxembourg"),
                "MO" => array("MAC", 446, _("Macao"), "Macao"),
                "MK" => array("MKD", 807, _("Macedonia, the former Yugoslav Republic of"), "Macedonia, the former Yugoslav Republic of"),
                "MG" => array("MDG", 450, _("Madagascar"), "Madagascar"),
                "MW" => array("MWI", 454, _("Malawi"), "Malawi"),
                "MY" => array("MYS", 458, _("Malaysia"), "Malaysia"),
                "MV" => array("MDV", 462, _("Maldives"), "Maldives"),
                "ML" => array("MLI", 466, _("Mali"), "Mali"),
                "MT" => array("MLT", 470, _("Malta"), "Malta"),
                "MH" => array("MHL", 584, _("Marshall Islands"), "Marshall Islands"),
                "MQ" => array("MTQ", 474, _("Martinique"), "Martinique"),
                "MR" => array("MRT", 478, _("Mauritania"), "Mauritania"),
                "MU" => array("MUS", 480, _("Mauritius"), "Mauritius"),
                "YT" => array("MYT", 175, _("Mayotte"), "Mayotte"),
                "MX" => array("MEX", 484, _("Mexico"), "Mexico"),
                "FM" => array("FSM", 583, _("Micronesia, Federated States of"), "Micronesia, Federated States of"),
                "MD" => array("MDA", 498, _("Moldova, Republic of"), "Moldova, Republic of"),
                "MC" => array("MCO", 492, _("Monaco"), "Monaco"),
                "MN" => array("MNG", 496, _("Mongolia"), "Mongolia"),
                "ME" => array("MNE", 499, _("Montenegro"), "Montenegro"),
                "MS" => array("MSR", 500, _("Montserrat"), "Montserrat"),
                "MA" => array("MAR", 504, _("Morocco"), "Morocco"),
                "MZ" => array("MOZ", 508, _("Mozambique"), "Mozambique"),
                "MM" => array("MMR", 104, _("Myanmar"), "Myanmar"),
                "NA" => array("NAM", 516, _("Namibia"), "Namibia"),
                "NR" => array("NRU", 520, _("Nauru"), "Nauru"),
                "NP" => array("NPL", 524, _("Nepal"), "Nepal"),
                "NL" => array("NLD", 528, _("Netherlands"), "Netherlands"),
                "AN" => array("ANT", 530, _("Netherlands Antilles"), "Netherlands Antilles"),
                "NC" => array("NCL", 540, _("New Caledonia"), "New Caledonia"),
                "NZ" => array("NZL", 554, _("New Zealand"), "New Zealand"),
                "NI" => array("NIC", 558, _("Nicaragua"), "Nicaragua"),
                "NE" => array("NER", 562, _("Niger"), "Niger"),
                "NG" => array("NGA", 566, _("Nigeria"), "Nigeria"),
                "NU" => array("NIU", 570, _("Niue"), "Niue"),
                "NF" => array("NFK", 574, _("Norfolk Island"), "Norfolk Island"),
                "MP" => array("MNP", 580, _("Northern Mariana Islands"), "Northern Mariana Islands"),
                "NO" => array("NOR", 578, _("Norway"), "Norway"),
                "OM" => array("OMN", 512, _("Oman"), "Oman"),
                "PK" => array("PAK", 586, _("Pakistan"), "Pakistan"),
                "PW" => array("PLW", 585, _("Palau"), "Palau"),
                "PS" => array("PSE", 275, _("Palestinian Territory"), "Palestinian Territory"),
                "PA" => array("PAN", 591, _("Panama"), "Panama"),
                "PG" => array("PNG", 598, _("Papua New Guinea"), "Papua New Guinea"),
                "PY" => array("PRY", 600, _("Paraguay"), "Paraguay"),
                "PE" => array("PER", 604, _("Peru"), "Peru"),
                "PH" => array("PHL", 608, _("Philippines"), "Philippines"),
                "PN" => array("PCN", 612, _("Pitcairn"), "Pitcairn"),
                "PL" => array("POL", 616, _("Poland"), "Poland"),
                "PT" => array("PRT", 620, _("Portugal"), "Portugal"),
                "PR" => array("PRI", 630, _("Puerto Rico"), "Puerto Rico"),
                "QA" => array("QAT", 634, _("Qatar"), "Qatar"),
                "RE" => array("REU", 638, _("Réunion"), "Réunion"),
                "RO" => array("ROU", 642, _("Romania"), "Romania"),
                "RU" => array("RUS", 643, _("Russian Federation"), "Russian Federation"),
                "RW" => array("RWA", 646, _("Rwanda"), "Rwanda"),
                "BL" => array("BLM", 652, _("Saint Barthélemy"), "Saint Barthélemy"),
                "SH" => array("SHN", 654, _("Saint Helena, Ascension and Tristan da Cunha"), "Saint Helena, Ascension and Tristan da Cunha"),
                "KN" => array("KNA", 659, _("Saint Kitts and Nevis"), "Saint Kitts and Nevis"),
                "LC" => array("LCA", 662, _("Saint Lucia"), "Saint Lucia"),
                "MF" => array("MAF", 663, _("Saint Martin (French part)"), "Saint Martin (French part)"),
                "PM" => array("SPM", 666, _("Saint Pierre and Miquelon"), "Saint Pierre and Miquelon"),
                "VC" => array("VCT", 670, _("Saint Vincent and the Grenadines"), "Saint Vincent and the Grenadines"),
                "WS" => array("WSM", 882, _("Samoa"), "Samoa"),
                "SM" => array("SMR", 674, _("San Marino"), "San Marino"),
                "ST" => array("STP", 678, _("Sao Tome and Principe"), "Sao Tome and Principe"),
                "SA" => array("SAU", 682, _("Saudi Arabia"), "Saudi Arabia"),
                "SN" => array("SEN", 686, _("Senegal"), "Senegal"),
                "RS" => array("SRB", 688, _("Serbia"), "Serbia"),
                "SC" => array("SYC", 690, _("Seychelles"), "Seychelles"),
                "SL" => array("SLE", 694, _("Sierra Leone"), "Sierra Leone"),
                "SG" => array("SGP", 702, _("Singapore"), "Singapore"),
                "SK" => array("SVK", 703, _("Slovakia"), "Slovakia"),
                "SI" => array("SVN", 705, _("Slovenia"), "Slovenia"),
                "SB" => array("SLB", 90, _("Solomon Islands"), "Solomon Islands"),
                "SO" => array("SOM", 706, _("Somalia"), "Somalia"),
                "ZA" => array("ZAF", 710, _("South Africa"), "South Africa"),
                "GS" => array("SGS", 239, _("South Georgia and the South Sandwich Islands"), "South Georgia and the South Sandwich Islands"),
                "ES" => array("ESP", 724, _("Spain"), "Spain"),
                "LK" => array("LKA", 144, _("Sri Lanka"), "Sri Lanka"),
                "SD" => array("SDN", 736, _("Sudan"), "Sudan"),
                "SR" => array("SUR", 740, _("Suriname"), "Suriname"),
                "SJ" => array("SJM", 744, _("Svalbard and Jan Mayen"), "Svalbard and Jan Mayen"),
                "SZ" => array("SWZ", 748, _("Swaziland"), "Swaziland"),
                "SE" => array("SWE", 752, _("Sweden"), "Sweden"),
                "CH" => array("CHE", 756, _("Switzerland"), "Switzerland"),
                "SY" => array("SYR", 760, _("Syrian Arab Republic"), "Syrian Arab Republic"),
                "TW" => array("TWN", 158, _("Taiwan, Province of China"), "Taiwan, Province of China"),
                "TJ" => array("TJK", 762, _("Tajikistan"), "Tajikistan"),
                "TZ" => array("TZA", 834, _("Tanzania, United Republic of"), "Tanzania, United Republic of"),
                "TH" => array("THA", 764, _("Thailand"), "Thailand"),
                "TL" => array("TLS", 626, _("Timor-Leste"), "Timor-Leste"),
                "TG" => array("TGO", 768, _("Togo"), "Togo"),
                "TK" => array("TKL", 772, _("Tokelau"), "Tokelau"),
                "TO" => array("TON", 776, _("Tonga"), "Tonga"),
                "TT" => array("TTO", 780, _("Trinidad and Tobago"), "Trinidad and Tobago"),
                "TN" => array("TUN", 788, _("Tunisia"), "Tunisia"),
                "TR" => array("TUR", 792, _("Turkey"), "Turkey"),
                "TM" => array("TKM", 795, _("Turkmenistan"), "Turkmenistan"),
                "TC" => array("TCA", 796, _("Turks and Caicos Islands"), "Turks and Caicos Islands"),
                "TV" => array("TUV", 798, _("Tuvalu"), "Tuvalu"),
                "UG" => array("UGA", 800, _("Uganda"), "Uganda"),
                "UA" => array("UKR", 804, _("Ukraine"), "Ukraine"),
                "AE" => array("ARE", 784, _("United Arab Emirates"), "United Arab Emirates"),
                "GB" => array("GBR", 826, _("United Kingdom"), "United Kingdom"),
                "US" => array("USA", 840, _("United States"), "United States"),
                "UM" => array("UMI", 581, _("United States Minor Outlying Islands"), "United States Minor Outlying Islands"),
                "UY" => array("URY", 858, _("Uruguay"), "Uruguay"),
                "UZ" => array("UZB", 860, _("Uzbekistan"), "Uzbekistan"),
                "VU" => array("VUT", 548, _("Vanuatu"), "Vanuatu"),
                "VE" => array("VEN", 862, _("Venezuela, Bolivarian Republic of"), "Venezuela, Bolivarian Republic of"),
                "VN" => array("VNM", 704, _("Viet Nam"), "Viet Nam"),
                "VG" => array("VGB", 92, _("Virgin Islands, British"), "Virgin Islands, British"),
                "VI" => array("VIR", 850, _("Virgin Islands, U.S."), "Virgin Islands, U.S."),
                "WF" => array("WLF", 876, _("Wallis and Futuna"), "Wallis and Futuna"),
                "EH" => array("ESH", 732, _("Western Sahara"), "Western Sahara"),
                "YE" => array("YEM", 887, _("Yemen"), "Yemen"),
                "ZM" => array("ZMB", 894, _("Zambia"), "Zambia"),
                "ZW" => array("ZWE", 716, _("Zimbabwe"), "Zimbabwe"),
            );
        }
        return $country_names;
    }
}

?>
