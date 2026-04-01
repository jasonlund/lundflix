<?php

return [
    'plex' => [
        'auth_failed' => "Yeah… so, we weren't able to authenticate your Plex user. That's… not great.",
        'no_access' => "Mmm yeah… I'm gonna need you to have access to lundflix. So… yeah.",
        'already_linked' => "Yeah… it looks like someone's already using this Plex account. So… yeah.",
        'multi_server_intro' => 'Yeah… so,',
        'multi_server_middle' => 'is available on',
        'multi_server_outro' => 'and you can go ahead and open them below.',
        'pin_creation_failed' => "Yeah… so, we had a little trouble connecting to Plex. I'm gonna need you to go ahead and try again. That'd be great.",
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
    'cart' => [
        'checkout_hint' => "Yeah… so, go ahead and hit Submit Request when you're ready. And make sure you use the new cover sheet on that. That'd be great.",
    ],
    'empty' => [
        'cart' => "Yeah… so, your cart is empty. Go ahead and add something. That'd be great.",
        'search_prompt' => 'Yeah… go ahead and type at least two characters to start searching.',
        'search_no_results' => "Mmm yeah… that search didn't turn up anything.",
        'search_no_results_filter' => "I'm gonna need you to refine your search term and... mmm.... filter by language.",
        'imdb_not_found' => "Yeah… so, we couldn't find that IMDb ID. I'm gonna need you to go ahead and come in tomorrow and… double-check it. That'd be great.",
        'episodes' => "Yeah… so, there aren't any episodes available right now.",
        'requests' => "Yeah… so, you haven't submitted any requests yet. I'm gonna need you to go ahead and search for something, add it to your cart, and submit a request. That'd be great.",
    ],
    'error' => [
        'episodes_backoff' => "Yeah… so, we had a little trouble loading the episodes. I'm gonna need you to go ahead and try again in about an hour. That'd be great.",
    ],
    'toast' => [
        'cart_added' => "Yeah… so, :title has been added to your cart. If you could add more or check out, that'd be great.",
        'cart_removed' => 'Mmm yeah… :title has been removed from your cart. So… yeah.',
        'episodes_added' => '{1} Yeah… so, :count episode of :title has been added to your cart. So… yeah.|[2,*] Yeah… so, :count episodes of :title have been added to your cart. So… yeah.',
        'episodes_removed' => '{1} Mmm yeah… :count episode of :title has been removed from your cart.|[2,*] Mmm yeah… :count episodes of :title have been removed from your cart.',
        'episodes_swapped' => "Yeah… so, your :title episodes have been updated. If you could go ahead and review your cart, that'd be great.",
        'request_submitted' => '{1} Yeah… so, :count item has been requested. Oh, and I\'m gonna need you to come in on Sunday too. That\'d be great.|[2,*] Yeah… so, :count items have been requested. Oh, and I\'m gonna need you to come in on Sunday too. That\'d be great.',
        'subscribed' => "Yeah… so, you're now subscribed to :title. We'll keep you in the loop. That'd be great.",
        'unsubscribed' => "Mmm yeah… you've been unsubscribed from :title. So… yeah.",
    ],
    'tooltip' => [
        'subscribe' => 'Yeah… go ahead and subscribe to this.',
        'unsubscribe' => 'Mmm yeah… click to unsubscribe.',
        'subscribe_disabled' => "Yeah… so, subscribing to this wouldn't really help anyone.",
    ],
    'dashboard' => [
        'last_fulfilled' => '{1} Mmkay… we added :count item for you :when.|[2,*] Mmkay… we added :count items for you :when.',
        'pending' => "{1} Yeah… so, you've got :count item pending. I'm gonna need you to run that by the Bobs.|[2,*] Yeah… so, you've got :count items pending. I'm gonna need you to run those by the Bobs.",
        'review_requests' => "Go ahead and review all your requests below. That'd be great.",
        'requests_heading' => 'Your Requests, Mmkay?',
        'no_matching_requests' => "Mmkay… none of your requests match those filters. I'm gonna need you to adjust them. That'd be great.",
        'when_today' => 'today',
        'when_yesterday' => 'yesterday',
        'when_days_ago' => '{1} :count day ago|[2,*] :count days ago',
        'greeting_new' => "Yeah… so, welcome to lundflix. Mmkay… I don't see a request from you yet.<br>I'm gonna need you to go ahead and search for a movie or show, add it to your cart, and submit a request.<br>Oh, and make sure you use the new cover sheet on that. That'd be great.",
    ],
    'loading' => [
        'skeleton' => "Yeah… so, we're loading that for you. If you could just hold on a moment, that'd be great.",
        'please_wait' => "Mmm yeah… I'm gonna need you to wait while we get that ready. So… yeah.",
        'fetching' => "Yeah… we're fetching that content right now. Just sit tight.",
    ],
];
