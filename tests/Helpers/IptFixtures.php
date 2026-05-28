<?php

if (! function_exists('fakeIptTorrentRow')) {
    function fakeIptTorrentRow(
        int $torrentId = 12345,
        string $name = 'Test.Torrent.2024.1080p.WEB-DL.x264-GROUP',
        string $size = '4.2 GB',
        int $seeders = 50,
        int $leechers = 5,
        int $snatches = 200,
        string $uploaded = '35.0 seconds ago by Uploader',
        int $category = 20,
    ): string {
        $filename = str_replace(' ', '.', $name);

        return <<<HTML
            <tr>
                <td><a href="?{$category}"><img src="/cat.png" alt="Cat"></a></td>
                <td><a class="hv" href="/t/{$torrentId}">{$name}</a> <span class="tag">New</span><div class="sub">{$uploaded}</div></td>
                <td><a href="/t/{$torrentId}?bookmark" class="tTipWrap"><i class="fa fa-star fa-2x"></i></a></td>
                <td><a href="/download.php/{$torrentId}/{$filename}.torrent" class="tTipWrap"><i class="fa fa-download fa-2x grn"></i></a></td>
                <td><a href="/t/{$torrentId}?page=0#startcomments" class="tTipWrap">0</a></td>
                <td>{$size}</td>
                <td>{$seeders}</td>
                <td>{$leechers}</td>
                <td>{$snatches}</td>
            </tr>
        HTML;
    }
}

if (! function_exists('fakeIptSearchHtml')) {
    function fakeIptSearchHtml(array $rows = []): string
    {
        $rowsHtml = implode("\n", $rows);

        return <<<HTML
            <html>
            <head><title>Torrents - IPTorrents - #1 Private Tracker</title></head>
            <body>
            <table id="torrents">
                <thead><tr><th>Cat</th><th>Name</th><th>BM</th><th>DL</th><th>Cmt</th><th>Size</th><th>S</th><th>L</th><th>Sn</th></tr></thead>
                <tbody>
                {$rowsHtml}
                </tbody>
            </table>
            </body>
            </html>
        HTML;
    }
}

if (! function_exists('fakeIptLoginPage')) {
    function fakeIptLoginPage(): string
    {
        return <<<'HTML'
            <html>
            <head><title>IPTorrents :: Login</title></head>
            <body><form action="/take_login.php"><input name="username" /><input name="password" /></form></body>
            </html>
        HTML;
    }
}

if (! function_exists('fakeIptTorrentDetailPage')) {
    function fakeIptTorrentDetailPage(string $imdbId = 'tt7654321'): string
    {
        return <<<HTML
            <html>
            <head><title>Test Torrent - IPTorrents - #1 Private Tracker</title></head>
            <body>
            <table><tr><td style="display:flex;gap:8px;">
                <a href="https://www.themoviedb.org/tv/12345/" target="_blank">TMDB</a>
                <a href="https://www.imdb.com/title/{$imdbId}/" target="_blank">IMDb</a>
            </td></tr></table>
            </body>
            </html>
        HTML;
    }
}

if (! function_exists('fakeIptTorrentDetailPageWithoutImdb')) {
    function fakeIptTorrentDetailPageWithoutImdb(): string
    {
        return <<<'HTML'
            <html>
            <head><title>Test Torrent - IPTorrents - #1 Private Tracker</title></head>
            <body>
            <table><tr><td style="display:flex;gap:8px;">
                <a href="https://www.themoviedb.org/tv/12345/" target="_blank">TMDB</a>
            </td></tr></table>
            </body>
            </html>
        HTML;
    }
}
