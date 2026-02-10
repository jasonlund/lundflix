<?php

namespace App\Enums;

use Illuminate\Support\Facades\Vite;

/**
 * Broadcast network logos mapped by TVMaze network ID.
 *
 * Source: https://github.com/tv-logo/tv-logos
 */
enum NetworkLogo: int
{
    // US
    case NbcUs = 1;
    case CbsUs = 2;
    case AbcUs = 3;
    case FoxUs = 4;
    case TheCwUs = 5;
    case HboUs = 8;
    case ShowtimeUs = 9;
    case AdultSwimUs = 10;
    case CartoonNetworkUs = 11;
    case FxUs = 13;
    case TntUs = 14;
    case SyfyUs = 16;
    case StarzUs = 17;
    case LifetimeUs = 18;
    case AmcUs = 20;
    case MtvUs = 22;
    case ComedyCentralUs = 23;
    case DisneyXdUs = 25;
    case FreeformUs = 26;
    case NickelodeonUs = 27;
    case AAndEUs = 29;
    case UsaUs = 30;
    case TbsUs = 32;
    case ParamountNetworkUs = 34;
    case CnnUs = 40;
    case NationalGeographicUs = 42;
    case BravoUs = 52;
    case HistoryChannelUs = 53;
    case BetUs = 56;
    case DiscoveryChannelUs = 66;
    case DisneyChannelUs = 78;
    case TlcUs = 80;
    case FoodNetworkUs = 81;
    case TravelChannelUs = 82;
    case PbsUs = 85;
    case InvestigationDiscoveryUs = 89;
    case AnimalPlanetUs = 92;
    case HgtvUs = 192;

    // UK
    case BbcOneUk = 12;
    case Itv1Uk = 35;
    case BbcTwoUk = 37;
    case Channel4Uk = 45;
    case BbcFourUk = 51;
    case Channel5Uk = 135;

    // AU
    case AbcAu = 114;
    case SbsAu = 127;

    public function file(): string
    {
        return match ($this) {
            self::NbcUs => 'nbc-us.png',
            self::CbsUs => 'cbs-logo-white-us.png',
            self::AbcUs => 'abc-us.png',
            self::FoxUs => 'fox-us.png',
            self::TheCwUs => 'the-cw-us.png',
            self::HboUs => 'hbo-us.png',
            self::ShowtimeUs => 'showtime-us.png',
            self::AdultSwimUs => 'adult-swim-us.png',
            self::CartoonNetworkUs => 'cartoon-network-us.png',
            self::FxUs => 'fx-us.png',
            self::TntUs => 'tnt-us.png',
            self::SyfyUs => 'syfy-us.png',
            self::StarzUs => 'starz-us.png',
            self::LifetimeUs => 'lifetime-us.png',
            self::AmcUs => 'amc-us.png',
            self::MtvUs => 'mtv-us.png',
            self::ComedyCentralUs => 'comedy-central-us.png',
            self::DisneyXdUs => 'disney-xd-us.png',
            self::FreeformUs => 'freeform-us.png',
            self::NickelodeonUs => 'nickelodeon-us.png',
            self::AAndEUs => 'a-and-e-us.png',
            self::UsaUs => 'usa-us.png',
            self::TbsUs => 'tbs-us.png',
            self::ParamountNetworkUs => 'paramount-network-us.png',
            self::CnnUs => 'cnn-us.png',
            self::NationalGeographicUs => 'national-geographic-us.png',
            self::BravoUs => 'bravo-us.png',
            self::HistoryChannelUs => 'history-channel-us.png',
            self::BetUs => 'bet-us.png',
            self::DiscoveryChannelUs => 'discovery-channel-us.png',
            self::DisneyChannelUs => 'disney-channel-us.png',
            self::TlcUs => 'tlc-us.png',
            self::FoodNetworkUs => 'food-network-us.png',
            self::TravelChannelUs => 'travel-channel-us.png',
            self::PbsUs => 'pbs-us.png',
            self::InvestigationDiscoveryUs => 'investigation-discovery-us.png',
            self::AnimalPlanetUs => 'animal-planet-us.png',
            self::HgtvUs => 'hgtv-us.png',
            self::BbcOneUk => 'bbc-one-uk.png',
            self::Itv1Uk => 'itv-1-uk.png',
            self::BbcTwoUk => 'bbc-two-uk.png',
            self::Channel4Uk => 'channel-4-uk.png',
            self::BbcFourUk => 'bbc-four-uk.png',
            self::Channel5Uk => 'channel-5-uk.png',
            self::AbcAu => 'abc-au.png',
            self::SbsAu => 'sbs-au.png',
        };
    }

    public function source(): string
    {
        return match ($this) {
            self::NbcUs, self::CbsUs, self::AbcUs, self::FoxUs, self::TheCwUs,
            self::HboUs, self::ShowtimeUs, self::AdultSwimUs, self::CartoonNetworkUs,
            self::FxUs, self::TntUs, self::SyfyUs, self::StarzUs, self::LifetimeUs,
            self::AmcUs, self::MtvUs, self::ComedyCentralUs, self::DisneyXdUs,
            self::FreeformUs, self::NickelodeonUs, self::AAndEUs, self::UsaUs,
            self::TbsUs, self::ParamountNetworkUs, self::CnnUs, self::NationalGeographicUs,
            self::BravoUs, self::HistoryChannelUs, self::BetUs, self::DiscoveryChannelUs,
            self::DisneyChannelUs, self::TlcUs, self::FoodNetworkUs, self::TravelChannelUs,
            self::PbsUs, self::InvestigationDiscoveryUs, self::AnimalPlanetUs,
            self::HgtvUs => "countries/united-states/{$this->file()}",
            self::BbcOneUk, self::Itv1Uk, self::BbcTwoUk, self::Channel4Uk,
            self::BbcFourUk, self::Channel5Uk => "countries/united-kingdom/{$this->file()}",
            self::AbcAu, self::SbsAu => "countries/australia/{$this->file()}",
        };
    }

    public function url(): string
    {
        return Vite::asset("resources/images/logos/networks/{$this->file()}");
    }
}
