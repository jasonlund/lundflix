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
        'search_no_results' => 'Mmm yeah… nothing matched that search. Try another title.',
        'episodes' => "Yeah… so, there aren't any episodes available right now.",
        'search_imdb_hint' => "Yeah… if you can't find what you're looking for, go ahead and try an :imdb_link ID instead. That'd be great.",
    ],
    'error' => [
        'episodes_backoff' => "Yeah… so, we had a little trouble loading the episodes. I'm gonna need you to go ahead and try again in about an hour. That'd be great.",
    ],
    'toast' => [
        'cart_added' => "Yeah… so, :title has been added to your cart. If you could add more or check out, that'd be great.",
        'cart_removed' => 'Mmm yeah… :title has been removed from your cart. So… yeah.',
        'episodes_added' => '{1} Yeah… so, :count episode of :title has been added to your cart. So… yeah.|[2,*] Yeah… so, :count episodes of :title have been added to your cart. So… yeah.',
        'episodes_removed' => '{1} Mmm yeah… :count episode of :title has been removed from your cart.|[2,*] Mmm yeah… :count episodes of :title have been removed from your cart.',
    ],
    'loading' => [
        'skeleton' => "Yeah… so, we're loading that for you. If you could just hold on a moment, that'd be great.",
        'please_wait' => "Mmm yeah… I'm gonna need you to wait while we get that ready. So… yeah.",
        'fetching' => "Yeah… we're fetching that content right now. Just sit tight.",
    ],
];
