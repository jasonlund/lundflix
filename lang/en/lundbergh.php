<?php

return [
    'plex' => [
        'auth_failed' => "Yeah… so, we weren't able to authenticate your Plex user. That's… not great.",
        'no_access' => "Mmm yeah… I'm gonna need you to have access to lundflix. So… yeah.",
        'already_linked' => "Yeah… it looks like someone's already using this Plex account. So… yeah.",
    ],
    'auth' => [
        'failed' => "Yeah… those credentials don't match our records. So… yeah.",
        'password' => "Mmm yeah… that password's not right. That's… not great.",
        'throttle' => "Yeah… you've tried too many times. I'm gonna need you to wait :seconds seconds.",
    ],
    'form' => [
        'email_description' => "Yeah… if you could use the address associated with your Plex account, that'd be great.",
        'plex_redirect' => "Mmm yeah… so you're gonna be redirected to plex.tv to authenticate your account and verify your access for registration.\n\nYeah… if you don't already have access to the lundflix server, I'm gonna need you to go ahead and not waste everyone's time here. That'd be great.",
    ],
    'empty' => [
        'cart_dropdown' => "Yeah… so, your cart is empty. Go ahead and add something. That'd be great.",
        'cart_checkout' => "Mmm yeah… your cart is empty. Search for movies and shows to add to your request. That'd be great.",
        'search_prompt' => 'Yeah… go ahead and type at least two characters to start searching.',
        'search_no_results' => "Mmm yeah… that search didn't turn up anything.",
        'search_no_results_filter' => "I'm gonna need you to refine your search term and... mmm.... filter by language.",
        'imdb_not_found' => "Yeah… so, we couldn't find that IMDb ID. I'm gonna need you to go ahead and come in tomorrow and… double-check it. That'd be great.",
        'episodes' => "Yeah… so, there aren't any episodes available right now.",
    ],
    'error' => [
        'episodes_backoff' => "Yeah… so, we had a little trouble loading the episodes. I'm gonna need you to go ahead and try again in about an hour. That'd be great.",
    ],
    'loading' => [
        'skeleton' => "Yeah… so, we're loading that for you. If you could just hold on a moment, that'd be great.",
        'please_wait' => "Mmm yeah… I'm gonna need you to wait while we get that ready. So… yeah.",
        'fetching' => "Yeah… we're fetching that content right now. Just sit tight.",
    ],
];
