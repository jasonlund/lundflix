<?php

/*
|--------------------------------------------------------------------------
| Lundberghese voice model (Bill Lumbergh, "Office Space")
|--------------------------------------------------------------------------
|
| Reference for writing/editing strings below so the voice stays consistent.
| The voice is a soft, slow, monotone manager who buries every demand in
| bureaucratic padding so it never sounds like an order.
|
| Formula:
|   "Yeah… so, [soft framing]. I'm gonna need you to go ahead and [the ask].
|   [optional buried tack-on.] That'd be great."
|
| Rules:
|   - Stall before asking: open on "Yeah…", "Mmm yeah…", or "Mmkay…".
|   - Pad the verb with "go ahead and" (semantically empty, always present).
|   - Soften the imperative: "I'm gonna need you to…", "If you could just…".
|   - Close on flat reassurance: "That'd be great." / "Thanks a bunch." / "So… yeah."
|   - Match valence to context:
|       * recoverable ask / success / neutral -> "That'd be great." or "So… yeah."
|       * dead-end failure (nothing the user can do) -> "That's… not great."
|     Don't pair a cheerful "That'd be great." with a hard failure, or mope
|     "That's… not great." onto a success.
|   - Don't bookend the same filler: if you open on "Mmkay…", don't also close
|     or echo "mmkay"; vary the stall across the line. Note "Yeah… so," and
|     "So… yeah." count as the SAME filler mirrored — never pair them (or any
|     "…yeah" opener with a "So… yeah." closer).
|   - Diffuse agency with passive/collective voice ("we're doing X now"), never "I want".
|   - Bury the worst part as an afterthought ("Oh, and I almost forgot…").
|   - Never apologise, never sound urgent or excited (no "!"), no genuine empathy.
|   - Use "…" for the trailing-monotone pauses. No em dashes (project rule).
|   - Props to reuse: cover sheets, the memo, come in Sunday, the Bobs.
|
| Placeholders: :title :count :seconds :when (and pluralize via trans_choice).
|
*/

return [
    'plex' => [
        'pin_creation_failed' => "Yeah… so, we had a little trouble connecting to Plex. I'm gonna need you to go ahead and try again. That'd be great.",
        'auth_failed' => "Yeah… so, we weren't able to authenticate your Plex user. That's… not great.",
        'no_access' => "Mmm yeah… so, you don't actually have access to lundflix. I'm gonna need you to go ahead and not waste everyone's time here. That's… not great.",
        'already_linked' => "Yeah… so, it looks like someone's already using this Plex account.\nIf you forgot your password, you can go ahead and reset it below. That'd be great.",
        'password_recovery_no_account' => "Mmm yeah… we couldn't find an account linked to that Plex user. I'm gonna need you to go ahead and register first. That'd be great.",
        'multi_server_intro' => 'Yeah… so,',
        'multi_server_middle' => 'is available on',
        'multi_server_outro' => 'and you can go ahead and open them below. That\'d be great.',
    ],
    'auth' => [
        'failed' => "Yeah… so, those credentials don't match our records.\nIf you forgot your password, you can go ahead and reset it below. That'd be great.",
        'password' => "Mmm yeah… that password's not right. That's… not great.",
        'throttle' => "Yeah… so, you've tried too many times. I'm gonna need you to wait :seconds seconds. That'd be great.",
    ],
    'form' => [
        'email_description' => "Yeah… if you could use the address associated with your Plex account, that'd be great.",
        'plex_redirect' => "Mmm yeah… so you're gonna be redirected to plex.tv to authenticate your account and verify your access for registration.\n\nYeah… if you don't already have access to the lundflix server, I'm gonna need you to go ahead and not waste everyone's time here. That'd be great.",
        'plex_password_reset' => "Yeah… so, you forgot your password. That's… not great.\n\nI'm gonna need you to go ahead and sign in with Plex to verify your identity. Once we confirm you are who you say you are, you can set a new password. That'd be great.",
        'password_reset_verified' => "Mmkay… so, we've confirmed you are who you say you are. Go ahead and set a new password below. And make sure it's something you'll actually remember this time. That'd be great.",
        'profile_password_hint' => "Yeah… so, if you need to change your password, I'm gonna need you to go ahead and log out, then use the Forgot Password option on the login page. That'd be great.",
    ],
    'cart' => [
        'checkout_hint' => "Yeah… so, go ahead and hit Submit Request when you're ready. And make sure you use the new cover sheet on that. That'd be great.",
    ],
    'empty' => [
        'cart' => "Yeah… so, your cart is empty. Go ahead and add something. That'd be great.",
        'search_prompt' => "Yeah… go ahead and type at least two characters to start searching. That'd be great.",
        'search_no_results' => "Mmm yeah… that search didn't turn up anything. That's… not great.",
        'search_no_results_filter' => "I'm gonna need you to refine your search term and filter by language. That'd be great.",
        'imdb_not_found' => "Yeah… so, we couldn't find that IMDb ID. I'm gonna need you to go ahead and come in tomorrow and… double-check it. That'd be great.",
        'episodes' => "Yeah… so, there aren't any episodes available right now. That's… not great.",
        'requests' => "Yeah… so, you haven't submitted any requests yet. I'm gonna need you to go ahead and search for something, add it to your cart, and submit a request. That'd be great.",
        'subscriptions' => "Mmkay… you're not subscribed to anything yet. I'm gonna need you to go ahead and subscribe to a movie or show. That'd be great.",
    ],
    'error' => [
        'episodes_backoff' => "Yeah… so, we had a little trouble loading the episodes. I'm gonna need you to go ahead and try again in about an hour. That'd be great.",
        'no_servers' => "Yeah… so, I can't find any servers right now. That's… not great.",
    ],
    'toast' => [
        'cart_added' => "Yeah… so, :title has been added to your cart. If you could add more or check out, that'd be great.",
        'cart_removed' => 'Mmkay… :title has been removed from your cart. So… yeah.',
        'episodes_added' => "{1} Yeah… so, :count episode of :title has been added to your cart. That'd be great.|[2,*] Yeah… so, :count episodes of :title have been added to your cart. That'd be great.",
        'episodes_removed' => '{1} Mmkay… :count episode of :title has been removed from your cart. So… yeah.|[2,*] Mmkay… :count episodes of :title have been removed from your cart. So… yeah.',
        'episodes_swapped' => "Yeah… so, your :title episodes have been updated. If you could go ahead and review your cart, that'd be great.",
        'request_submitted' => '{1} Yeah… so, :count item has been requested. Oh, and I\'m gonna need you to come in on Sunday too. That\'d be great.|[2,*] Yeah… so, :count items have been requested. Oh, and I\'m gonna need you to come in on Sunday too. That\'d be great.',
        'subscribed' => "Yeah… so, you're now subscribed to :title. We'll keep you in the loop. That'd be great.",
        'unsubscribed' => "Mmkay… you've been unsubscribed from :title. So… yeah.",
        'mode_download' => "Yeah… so, we'll go ahead and grab :title for you automatically. That'd be great.",
        'mode_notify' => "Mmkay… we'll just notify you about :title, no downloading. So… yeah.",
        'profile_updated' => "Mmkay… your profile has been updated. That'd be great.",
    ],
    'dashboard' => [
        'last_fulfilled' => "{1} Mmkay… we added :count item for you :when. That'd be great.|[2,*] Mmkay… we added :count items for you :when. That'd be great.",
        'pending' => "{1} Yeah… so, you've got :count item pending. I'm gonna need you to run that by the Bobs. That'd be great.|[2,*] Yeah… so, you've got :count items pending. I'm gonna need you to run those by the Bobs. That'd be great.",
        'review_requests' => "Go ahead and review all your requests below. That'd be great.",
        'no_matching_requests' => "Mmkay… none of your requests match those filters. I'm gonna need you to adjust them. That'd be great.",
        'when_today' => 'today',
        'when_yesterday' => 'yesterday',
        'when_days_ago' => '{1} :count day ago|[2,*] :count days ago',
        'greeting_new' => "Yeah… hi. I'm… I'm Lundbergh. So, you can go ahead and run lundflix right from here, just by searching for a movie or show. Once you've found something, we'll go ahead and check if it's available on lundflix, or any of the servers down below.<br>Mmm yeah… and if it isn't out there yet, you can go ahead and request it, and we'll add it automatically if we can.<br>Mmkay… and for shows that are still on the air or movies still on their way to digital, if you could go ahead and subscribe, we'll add it the moment it shows up.<br>So… yeah. Oh, and I'm gonna need you to come in on Sunday too. That'd be great.",
        'no_recent_subscriptions' => "Mmkay… nothing's come out recently. So… yeah.",
    ],
    'loading' => [
        'skeleton' => "Yeah… so, we're loading that for you. If you could just hold on a moment, that'd be great.",
        'please_wait' => "Mmm yeah… I'm gonna need you to wait while we get that ready. That'd be great.",
        'fetching' => "Yeah… we're fetching that content right now. Just sit tight. That'd be great.",
    ],
    'credits' => [
        'intro' => 'Mmkay… legal wanted these somewhere. So… yeah.',
    ],
];
