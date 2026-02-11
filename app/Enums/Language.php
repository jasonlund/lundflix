<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;
use Illuminate\Support\Str;

enum Language: string implements HasLabel
{
    case Abkhazian = 'ab';
    case Afrikaans = 'af';
    case Akan = 'ak';
    case Amharic = 'am';
    case Arabic = 'ar';
    case Assamese = 'as';
    case Aymara = 'ay';
    case Azerbaijani = 'az';
    case Bashkir = 'ba';
    case Belarusian = 'be';
    case Bulgarian = 'bg';
    case Bislama = 'bi';
    case Bambara = 'bm';
    case Bengali = 'bn';
    case Tibetan = 'bo';
    case Breton = 'br';
    case Bosnian = 'bs';
    case Catalan = 'ca';
    case Chechen = 'ce';
    case Cantonese = 'cn';
    case Corsican = 'co';
    case Cree = 'cr';
    case Czech = 'cs';
    case Chuvash = 'cv';
    case Welsh = 'cy';
    case Danish = 'da';
    case German = 'de';
    case Divehi = 'dv';
    case Dzongkha = 'dz';
    case Greek = 'el';
    case English = 'en';
    case Esperanto = 'eo';
    case Spanish = 'es';
    case Estonian = 'et';
    case Basque = 'eu';
    case Persian = 'fa';
    case Fula = 'ff';
    case Finnish = 'fi';
    case Fijian = 'fj';
    case Faroese = 'fo';
    case French = 'fr';
    case WesternFrisian = 'fy';
    case Irish = 'ga';
    case ScottishGaelic = 'gd';
    case Galician = 'gl';
    case Guarani = 'gn';
    case Gujarati = 'gu';
    case Hausa = 'ha';
    case Hebrew = 'he';
    case Hindi = 'hi';
    case Croatian = 'hr';
    case HaitianCreole = 'ht';
    case Hungarian = 'hu';
    case Armenian = 'hy';
    case Indonesian = 'id';
    case Igbo = 'ig';
    case SichuanYi = 'ii';
    case Inupiaq = 'ik';
    case Icelandic = 'is';
    case Italian = 'it';
    case Inuktitut = 'iu';
    case Japanese = 'ja';
    case Javanese = 'jv';
    case Georgian = 'ka';
    case Kongo = 'kg';
    case Kikuyu = 'ki';
    case Kazakh = 'kk';
    case Kalaallisut = 'kl';
    case Khmer = 'km';
    case Kannada = 'kn';
    case Korean = 'ko';
    case Kashmiri = 'ks';
    case Kurdish = 'ku';
    case Cornish = 'kw';
    case Kyrgyz = 'ky';
    case Latin = 'la';
    case Luxembourgish = 'lb';
    case Ganda = 'lg';
    case Limburgish = 'li';
    case Lingala = 'ln';
    case Lao = 'lo';
    case Lithuanian = 'lt';
    case Latvian = 'lv';
    case Malagasy = 'mg';
    case Marshallese = 'mh';
    case Maori = 'mi';
    case Macedonian = 'mk';
    case Malayalam = 'ml';
    case Mongolian = 'mn';
    case Moldavian = 'mo';
    case Marathi = 'mr';
    case Malay = 'ms';
    case Maltese = 'mt';
    case Burmese = 'my';
    case NorwegianBokmal = 'nb';
    case NorthernNdebele = 'nd';
    case Nepali = 'ne';
    case Dutch = 'nl';
    case NorwegianNynorsk = 'nn';
    case Norwegian = 'no';
    case Navajo = 'nv';
    case Chichewa = 'ny';
    case Occitan = 'oc';
    case Oromo = 'om';
    case Odia = 'or';
    case Ossetian = 'os';
    case Panjabi = 'pa';
    case Polish = 'pl';
    case Pashto = 'ps';
    case Portuguese = 'pt';
    case Quechua = 'qu';
    case Romansh = 'rm';
    case Romanian = 'ro';
    case Russian = 'ru';
    case Kinyarwanda = 'rw';
    case Sanskrit = 'sa';
    case Sardinian = 'sc';
    case Sindhi = 'sd';
    case NorthernSami = 'se';
    case Sango = 'sg';
    case SerboCroatian = 'sh';
    case Sinhalese = 'si';
    case Slovak = 'sk';
    case Slovenian = 'sl';
    case Samoan = 'sm';
    case Shona = 'sn';
    case Somali = 'so';
    case Albanian = 'sq';
    case Serbian = 'sr';
    case Swati = 'ss';
    case SouthernSotho = 'st';
    case Sundanese = 'su';
    case Swedish = 'sv';
    case Swahili = 'sw';
    case Tamil = 'ta';
    case Telugu = 'te';
    case Tajik = 'tg';
    case Thai = 'th';
    case Tigrinya = 'ti';
    case Turkmen = 'tk';
    case Tagalog = 'tl';
    case Tswana = 'tn';
    case Turkish = 'tr';
    case Tsonga = 'ts';
    case Tatar = 'tt';
    case Twi = 'tw';
    case Tahitian = 'ty';
    case Uyghur = 'ug';
    case Ukrainian = 'uk';
    case Urdu = 'ur';
    case Uzbek = 'uz';
    case Venda = 've';
    case Vietnamese = 'vi';
    case Wolof = 'wo';
    case Xhosa = 'xh';
    case NoLanguage = 'xx';
    case Yiddish = 'yi';
    case Yoruba = 'yo';
    case Chinese = 'zh';
    case Zulu = 'zu';

    public function getLabel(): string
    {
        return Str::headline($this->name);
    }

    public static function tryFromName(string $name): ?self
    {
        static $lookup = null;

        if ($lookup === null) {
            $lookup = [];
            foreach (self::cases() as $case) {
                $lookup[strtolower($case->getLabel())] = $case;
            }
        }

        return $lookup[strtolower(trim($name))] ?? null;
    }

    public static function fromName(string $name): self
    {
        return self::tryFromName($name) ?? throw new \ValueError(
            "\"$name\" is not a valid language name for ".self::class
        );
    }
}
