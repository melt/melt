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
                "AF" => array("AFG", 4, _("Afghanistan")),
                "AX" => array("ALA", 248, _("Åland Islands")),
                "AL" => array("ALB", 8, _("Albania")),
                "DZ" => array("DZA", 12, _("Algeria")),
                "AS" => array("ASM", 16, _("American Samoa")),
                "AD" => array("AND", 20, _("Andorra")),
                "AO" => array("AGO", 24, _("Angola")),
                "AI" => array("AIA", 660, _("Anguilla")),
                "AQ" => array("ATA", 10, _("Antarctica")),
                "AG" => array("ATG", 28, _("Antigua and Barbuda")),
                "AR" => array("ARG", 32, _("Argentina")),
                "AM" => array("ARM", 51, _("Armenia")),
                "AW" => array("ABW", 533, _("Aruba")),
                "AU" => array("AUS", 36, _("Australia")),
                "AT" => array("AUT", 40, _("Austria")),
                "AZ" => array("AZE", 31, _("Azerbaijan")),
                "BS" => array("BHS", 44, _("Bahamas")),
                "BH" => array("BHR", 48, _("Bahrain")),
                "BD" => array("BGD", 50, _("Bangladesh")),
                "BB" => array("BRB", 52, _("Barbados")),
                "BY" => array("BLR", 112, _("Belarus")),
                "BE" => array("BEL", 56, _("Belgium")),
                "BZ" => array("BLZ", 84, _("Belize")),
                "BJ" => array("BEN", 204, _("Benin")),
                "BM" => array("BMU", 60, _("Bermuda")),
                "BT" => array("BTN", 64, _("Bhutan")),
                "BO" => array("BOL", 68, _("Bolivia, Plurinational State of")),
                "BA" => array("BIH", 70, _("Bosnia and Herzegovina")),
                "BW" => array("BWA", 72, _("Botswana")),
                "BV" => array("BVT", 74, _("Bouvet Island")),
                "BR" => array("BRA", 76, _("Brazil")),
                "IO" => array("IOT", 86, _("British Indian Ocean Territory")),
                "BN" => array("BRN", 96, _("Brunei Darussalam")),
                "BG" => array("BGR", 100, _("Bulgaria")),
                "BF" => array("BFA", 854, _("Burkina Faso")),
                "BI" => array("BDI", 108, _("Burundi")),
                "KH" => array("KHM", 116, _("Cambodia")),
                "CM" => array("CMR", 120, _("Cameroon")),
                "CA" => array("CAN", 124, _("Canada")),
                "CV" => array("CPV", 132, _("Cape Verde")),
                "KY" => array("CYM", 136, _("Cayman Islands")),
                "CF" => array("CAF", 140, _("Central African Republic")),
                "TD" => array("TCD", 148, _("Chad")),
                "CL" => array("CHL", 152, _("Chile")),
                "CN" => array("CHN", 156, _("China")),
                "CX" => array("CXR", 162, _("Christmas Island")),
                "CC" => array("CCK", 166, _("Cocos (Keeling) Islands")),
                "CO" => array("COL", 170, _("Colombia")),
                "KM" => array("COM", 174, _("Comoros")),
                "CG" => array("COG", 178, _("Congo")),
                "CD" => array("COD", 180, _("Congo, the Democratic Republic of the")),
                "CK" => array("COK", 184, _("Cook Islands")),
                "CR" => array("CRI", 188, _("Costa Rica")),
                "CI" => array("CIV", 384, _("Côte d'Ivoire")),
                "HR" => array("HRV", 191, _("Croatia")),
                "CU" => array("CUB", 192, _("Cuba")),
                "CY" => array("CYP", 196, _("Cyprus")),
                "CZ" => array("CZE", 203, _("Czech Republic")),
                "DK" => array("DNK", 208, _("Denmark")),
                "DJ" => array("DJI", 262, _("Djibouti")),
                "DM" => array("DMA", 212, _("Dominica")),
                "DO" => array("DOM", 214, _("Dominican Republic")),
                "EC" => array("ECU", 218, _("Ecuador")),
                "EG" => array("EGY", 818, _("Egypt")),
                "SV" => array("SLV", 222, _("El Salvador")),
                "GQ" => array("GNQ", 226, _("Equatorial Guinea")),
                "ER" => array("ERI", 232, _("Eritrea")),
                "EE" => array("EST", 233, _("Estonia")),
                "ET" => array("ETH", 231, _("Ethiopia")),
                "FK" => array("FLK", 238, _("Falkland Islands (Malvinas)")),
                "FO" => array("FRO", 234, _("Faroe Islands")),
                "FJ" => array("FJI", 242, _("Fiji")),
                "FI" => array("FIN", 246, _("Finland")),
                "FR" => array("FRA", 250, _("France")),
                "GF" => array("GUF", 254, _("French Guiana")),
                "PF" => array("PYF", 258, _("French Polynesia")),
                "TF" => array("ATF", 260, _("French Southern Territories")),
                "GA" => array("GAB", 266, _("Gabon")),
                "GM" => array("GMB", 270, _("Gambia")),
                "GE" => array("GEO", 268, _("Georgia")),
                "DE" => array("DEU", 276, _("Germany")),
                "GH" => array("GHA", 288, _("Ghana")),
                "GI" => array("GIB", 292, _("Gibraltar")),
                "GR" => array("GRC", 300, _("Greece")),
                "GL" => array("GRL", 304, _("Greenland")),
                "GD" => array("GRD", 308, _("Grenada")),
                "GP" => array("GLP", 312, _("Guadeloupe")),
                "GU" => array("GUM", 316, _("Guam")),
                "GT" => array("GTM", 320, _("Guatemala")),
                "GG" => array("GGY", 831, _("Guernsey")),
                "GN" => array("GIN", 324, _("Guinea")),
                "GW" => array("GNB", 624, _("Guinea-Bissau")),
                "GY" => array("GUY", 328, _("Guyana")),
                "HT" => array("HTI", 332, _("Haiti")),
                "HM" => array("HMD", 334, _("Heard Island and McDonald Islands")),
                "VA" => array("VAT", 336, _("Holy See (Vatican City State)")),
                "HN" => array("HND", 340, _("Honduras")),
                "HK" => array("HKG", 344, _("Hong Kong")),
                "HU" => array("HUN", 348, _("Hungary")),
                "IS" => array("ISL", 352, _("Iceland")),
                "IN" => array("IND", 356, _("India")),
                "ID" => array("IDN", 360, _("Indonesia")),
                "IR" => array("IRN", 364, _("Iran, Islamic Republic of")),
                "IQ" => array("IRQ", 368, _("Iraq")),
                "IE" => array("IRL", 372, _("Ireland")),
                "IM" => array("IMN", 833, _("Isle of Man")),
                "IL" => array("ISR", 376, _("Israel")),
                "IT" => array("ITA", 380, _("Italy")),
                "JM" => array("JAM", 388, _("Jamaica")),
                "JP" => array("JPN", 392, _("Japan")),
                "JE" => array("JEY", 832, _("Jersey")),
                "JO" => array("JOR", 400, _("Jordan")),
                "KZ" => array("KAZ", 398, _("Kazakhstan")),
                "KE" => array("KEN", 404, _("Kenya")),
                "KI" => array("KIR", 296, _("Kiribati")),
                "KP" => array("PRK", 408, _("Korea, Democratic People's Republic of")),
                "KR" => array("KOR", 410, _("Korea, Republic of")),
                "KW" => array("KWT", 414, _("Kuwait")),
                "KG" => array("KGZ", 417, _("Kyrgyzstan")),
                "LA" => array("LAO", 418, _("Lao People's Democratic Republic")),
                "LV" => array("LVA", 428, _("Latvia")),
                "LB" => array("LBN", 422, _("Lebanon")),
                "LS" => array("LSO", 426, _("Lesotho")),
                "LR" => array("LBR", 430, _("Liberia")),
                "LY" => array("LBY", 434, _("Libyan Arab Jamahiriya")),
                "LI" => array("LIE", 438, _("Liechtenstein")),
                "LT" => array("LTU", 440, _("Lithuania")),
                "LU" => array("LUX", 442, _("Luxembourg")),
                "MO" => array("MAC", 446, _("Macao")),
                "MK" => array("MKD", 807, _("Macedonia, the former Yugoslav Republic of")),
                "MG" => array("MDG", 450, _("Madagascar")),
                "MW" => array("MWI", 454, _("Malawi")),
                "MY" => array("MYS", 458, _("Malaysia")),
                "MV" => array("MDV", 462, _("Maldives")),
                "ML" => array("MLI", 466, _("Mali")),
                "MT" => array("MLT", 470, _("Malta")),
                "MH" => array("MHL", 584, _("Marshall Islands")),
                "MQ" => array("MTQ", 474, _("Martinique")),
                "MR" => array("MRT", 478, _("Mauritania")),
                "MU" => array("MUS", 480, _("Mauritius")),
                "YT" => array("MYT", 175, _("Mayotte")),
                "MX" => array("MEX", 484, _("Mexico")),
                "FM" => array("FSM", 583, _("Micronesia, Federated States of")),
                "MD" => array("MDA", 498, _("Moldova, Republic of")),
                "MC" => array("MCO", 492, _("Monaco")),
                "MN" => array("MNG", 496, _("Mongolia")),
                "ME" => array("MNE", 499, _("Montenegro")),
                "MS" => array("MSR", 500, _("Montserrat")),
                "MA" => array("MAR", 504, _("Morocco")),
                "MZ" => array("MOZ", 508, _("Mozambique")),
                "MM" => array("MMR", 104, _("Myanmar")),
                "NA" => array("NAM", 516, _("Namibia")),
                "NR" => array("NRU", 520, _("Nauru")),
                "NP" => array("NPL", 524, _("Nepal")),
                "NL" => array("NLD", 528, _("Netherlands")),
                "AN" => array("ANT", 530, _("Netherlands Antilles")),
                "NC" => array("NCL", 540, _("New Caledonia")),
                "NZ" => array("NZL", 554, _("New Zealand")),
                "NI" => array("NIC", 558, _("Nicaragua")),
                "NE" => array("NER", 562, _("Niger")),
                "NG" => array("NGA", 566, _("Nigeria")),
                "NU" => array("NIU", 570, _("Niue")),
                "NF" => array("NFK", 574, _("Norfolk Island")),
                "MP" => array("MNP", 580, _("Northern Mariana Islands")),
                "NO" => array("NOR", 578, _("Norway")),
                "OM" => array("OMN", 512, _("Oman")),
                "PK" => array("PAK", 586, _("Pakistan")),
                "PW" => array("PLW", 585, _("Palau")),
                "PS" => array("PSE", 275, _("Palestinian Territory")),
                "PA" => array("PAN", 591, _("Panama")),
                "PG" => array("PNG", 598, _("Papua New Guinea")),
                "PY" => array("PRY", 600, _("Paraguay")),
                "PE" => array("PER", 604, _("Peru")),
                "PH" => array("PHL", 608, _("Philippines")),
                "PN" => array("PCN", 612, _("Pitcairn")),
                "PL" => array("POL", 616, _("Poland")),
                "PT" => array("PRT", 620, _("Portugal")),
                "PR" => array("PRI", 630, _("Puerto Rico")),
                "QA" => array("QAT", 634, _("Qatar")),
                "RE" => array("REU", 638, _("Réunion")),
                "RO" => array("ROU", 642, _("Romania")),
                "RU" => array("RUS", 643, _("Russian Federation")),
                "RW" => array("RWA", 646, _("Rwanda")),
                "BL" => array("BLM", 652, _("Saint Barthélemy")),
                "SH" => array("SHN", 654, _("Saint Helena, Ascension and Tristan da Cunha")),
                "KN" => array("KNA", 659, _("Saint Kitts and Nevis")),
                "LC" => array("LCA", 662, _("Saint Lucia")),
                "MF" => array("MAF", 663, _("Saint Martin (French part)")),
                "PM" => array("SPM", 666, _("Saint Pierre and Miquelon")),
                "VC" => array("VCT", 670, _("Saint Vincent and the Grenadines")),
                "WS" => array("WSM", 882, _("Samoa")),
                "SM" => array("SMR", 674, _("San Marino")),
                "ST" => array("STP", 678, _("Sao Tome and Principe")),
                "SA" => array("SAU", 682, _("Saudi Arabia")),
                "SN" => array("SEN", 686, _("Senegal")),
                "RS" => array("SRB", 688, _("Serbia")),
                "SC" => array("SYC", 690, _("Seychelles")),
                "SL" => array("SLE", 694, _("Sierra Leone")),
                "SG" => array("SGP", 702, _("Singapore")),
                "SK" => array("SVK", 703, _("Slovakia")),
                "SI" => array("SVN", 705, _("Slovenia")),
                "SB" => array("SLB", 90, _("Solomon Islands")),
                "SO" => array("SOM", 706, _("Somalia")),
                "ZA" => array("ZAF", 710, _("South Africa")),
                "GS" => array("SGS", 239, _("South Georgia and the South Sandwich Islands")),
                "ES" => array("ESP", 724, _("Spain")),
                "LK" => array("LKA", 144, _("Sri Lanka")),
                "SD" => array("SDN", 736, _("Sudan")),
                "SR" => array("SUR", 740, _("Suriname")),
                "SJ" => array("SJM", 744, _("Svalbard and Jan Mayen")),
                "SZ" => array("SWZ", 748, _("Swaziland")),
                "SE" => array("SWE", 752, _("Sweden")),
                "CH" => array("CHE", 756, _("Switzerland")),
                "SY" => array("SYR", 760, _("Syrian Arab Republic")),
                "TW" => array("TWN", 158, _("Taiwan, Province of China")),
                "TJ" => array("TJK", 762, _("Tajikistan")),
                "TZ" => array("TZA", 834, _("Tanzania, United Republic of")),
                "TH" => array("THA", 764, _("Thailand")),
                "TL" => array("TLS", 626, _("Timor-Leste")),
                "TG" => array("TGO", 768, _("Togo")),
                "TK" => array("TKL", 772, _("Tokelau")),
                "TO" => array("TON", 776, _("Tonga")),
                "TT" => array("TTO", 780, _("Trinidad and Tobago")),
                "TN" => array("TUN", 788, _("Tunisia")),
                "TR" => array("TUR", 792, _("Turkey")),
                "TM" => array("TKM", 795, _("Turkmenistan")),
                "TC" => array("TCA", 796, _("Turks and Caicos Islands")),
                "TV" => array("TUV", 798, _("Tuvalu")),
                "UG" => array("UGA", 800, _("Uganda")),
                "UA" => array("UKR", 804, _("Ukraine")),
                "AE" => array("ARE", 784, _("United Arab Emirates")),
                "GB" => array("GBR", 826, _("United Kingdom")),
                "US" => array("USA", 840, _("United States")),
                "UM" => array("UMI", 581, _("United States Minor Outlying Islands")),
                "UY" => array("URY", 858, _("Uruguay")),
                "UZ" => array("UZB", 860, _("Uzbekistan")),
                "VU" => array("VUT", 548, _("Vanuatu")),
                "VE" => array("VEN", 862, _("Venezuela, Bolivarian Republic of")),
                "VN" => array("VNM", 704, _("Viet Nam")),
                "VG" => array("VGB", 92, _("Virgin Islands, British")),
                "VI" => array("VIR", 850, _("Virgin Islands, U.S.")),
                "WF" => array("WLF", 876, _("Wallis and Futuna")),
                "EH" => array("ESH", 732, _("Western Sahara")),
                "YE" => array("YEM", 887, _("Yemen")),
                "ZM" => array("ZMB", 894, _("Zambia")),
                "ZW" => array("ZWE", 716, _("Zimbabwe")),
            );
        }
        return $country_names;
    }
}

?>
