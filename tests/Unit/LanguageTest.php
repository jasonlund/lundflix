<?php

use App\Enums\Language;

it('creates a language from ISO code', function () {
    expect(Language::from('en'))->toBe(Language::English)
        ->and(Language::from('fr'))->toBe(Language::French)
        ->and(Language::from('ja'))->toBe(Language::Japanese);
});

it('returns null for unknown ISO code via tryFrom', function () {
    expect(Language::tryFrom('zz'))->toBeNull()
        ->and(Language::tryFrom(''))->toBeNull();
});

it('returns correct labels for common languages', function (Language $language, string $expected) {
    expect($language->getLabel())->toBe($expected);
})->with([
    [Language::English, 'English'],
    [Language::French, 'French'],
    [Language::Spanish, 'Spanish'],
    [Language::German, 'German'],
    [Language::Japanese, 'Japanese'],
    [Language::Korean, 'Korean'],
    [Language::Chinese, 'Chinese'],
    [Language::Arabic, 'Arabic'],
    [Language::Portuguese, 'Portuguese'],
    [Language::Russian, 'Russian'],
]);

it('returns correct labels for multi-word languages', function (Language $language, string $expected) {
    expect($language->getLabel())->toBe($expected);
})->with([
    [Language::ScottishGaelic, 'Scottish Gaelic'],
    [Language::WesternFrisian, 'Western Frisian'],
    [Language::HaitianCreole, 'Haitian Creole'],
    [Language::NorwegianBokmal, 'Norwegian Bokmal'],
    [Language::NorwegianNynorsk, 'Norwegian Nynorsk'],
    [Language::NorthernNdebele, 'Northern Ndebele'],
    [Language::NorthernSami, 'Northern Sami'],
    [Language::SouthernSotho, 'Southern Sotho'],
    [Language::SerboCroatian, 'Serbo Croatian'],
    [Language::SichuanYi, 'Sichuan Yi'],
    [Language::NoLanguage, 'No Language'],
]);

it('resolves language from full English name via fromName', function (string $name, Language $expected) {
    expect(Language::fromName($name))->toBe($expected);
})->with([
    ['English', Language::English],
    ['French', Language::French],
    ['Japanese', Language::Japanese],
    ['Scottish Gaelic', Language::ScottishGaelic],
    ['Sinhalese', Language::Sinhalese],
    ['Panjabi', Language::Panjabi],
]);

it('resolves language from name case-insensitively', function (string $name, Language $expected) {
    expect(Language::fromName($name))->toBe($expected);
})->with([
    ['english', Language::English],
    ['FRENCH', Language::French],
    ['scottish gaelic', Language::ScottishGaelic],
]);

it('handles trimmed whitespace in fromName', function () {
    expect(Language::fromName('  English  '))->toBe(Language::English)
        ->and(Language::fromName(' French '))->toBe(Language::French);
});

it('returns null for unknown name via tryFromName', function (string $name) {
    expect(Language::tryFromName($name))->toBeNull();
})->with([
    'empty string' => '',
    'unknown language' => 'Klingon',
    'gibberish' => 'xyzabc',
    'partial match' => 'Eng',
]);

it('throws ValueError for unknown name via fromName', function () {
    Language::fromName('Klingon');
})->throws(ValueError::class);

it('resolves all TVMaze language names', function (string $tvmazeName) {
    expect(Language::tryFromName($tvmazeName))->not->toBeNull();
})->with([
    'Afrikaans', 'Albanian', 'Arabic', 'Armenian', 'Azerbaijani',
    'Basque', 'Belarusian', 'Bengali', 'Bosnian', 'Bulgarian', 'Burmese',
    'Catalan', 'Chechen', 'Chinese', 'Croatian', 'Czech',
    'Danish', 'Divehi', 'Dutch',
    'English', 'Estonian',
    'Fijian', 'Finnish', 'French',
    'Galician', 'Georgian', 'German', 'Greek', 'Gujarati',
    'Hebrew', 'Hindi', 'Hungarian',
    'Icelandic', 'Indonesian', 'Irish', 'Italian',
    'Japanese', 'Javanese',
    'Kannada', 'Kazakh', 'Kongo', 'Korean', 'Kyrgyz',
    'Lao', 'Latin', 'Latvian', 'Lithuanian', 'Luxembourgish',
    'Malagasy', 'Malay', 'Malayalam', 'Marathi', 'Mongolian',
    'Norwegian',
    'Panjabi', 'Pashto', 'Persian', 'Polish', 'Portuguese',
    'Romanian', 'Russian',
    'Scottish Gaelic', 'Serbian', 'Sinhalese', 'Slovak', 'Slovenian', 'Spanish', 'Swahili', 'Swedish',
    'Tagalog', 'Tamil', 'Telugu', 'Thai', 'Turkish',
    'Ukrainian', 'Urdu', 'Uzbek',
    'Vietnamese',
    'Welsh', 'Wolof',
    'Yoruba',
    'Zulu',
]);

it('has unique backing values', function () {
    $values = array_map(fn (Language $lang) => $lang->value, Language::cases());

    expect($values)->toHaveCount(count(array_unique($values)));
});
