<?php

use App\Enums\RequestItemStatus;
use App\Filament\Resources\Requests\Pages\ViewRequest;
use App\Filament\Resources\Requests\RelationManagers\RequestItemsRelationManager;
use App\Models\Episode;
use App\Models\Movie;
use App\Models\Request;
use App\Models\RequestItem;
use App\Models\Show;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->actingAs($this->admin);
});

it('renders the request items relation manager on view page', function () {
    $request = Request::factory()->create();

    Livewire::test(ViewRequest::class, ['record' => $request->getRouteKey()])
        ->assertSeeLivewire(RequestItemsRelationManager::class);
});

it('displays movie request items', function () {
    $request = Request::factory()->create();
    $movie = Movie::factory()->create(['title' => 'Test Movie', 'year' => 2024]);
    $item = RequestItem::factory()->create([
        'request_id' => $request->id,
        'requestable_type' => Movie::class,
        'requestable_id' => $movie->id,
    ]);

    Livewire::test(RequestItemsRelationManager::class, [
        'ownerRecord' => $request,
        'pageClass' => ViewRequest::class,
    ])
        ->assertOk()
        ->assertCanSeeTableRecords([$item])
        ->assertSee('Test Movie (2024)');
});

it('displays episode request items', function () {
    $request = Request::factory()->create();
    $show = Show::factory()->create(['name' => 'Test Show']);
    $episode = Episode::factory()->create([
        'show_id' => $show->id,
        'season' => 1,
        'number' => 5,
    ]);
    $item = RequestItem::factory()->create([
        'request_id' => $request->id,
        'requestable_type' => Episode::class,
        'requestable_id' => $episode->id,
    ]);

    Livewire::test(RequestItemsRelationManager::class, [
        'ownerRecord' => $request,
        'pageClass' => ViewRequest::class,
    ])
        ->assertOk()
        ->assertCanSeeTableRecords([$item])
        ->assertSee('Test Show')
        ->assertSee('s01e05');
});

it('displays type badges for movies and episodes', function () {
    $request = Request::factory()->create();
    $movie = Movie::factory()->create();
    $show = Show::factory()->create();
    $episode = Episode::factory()->create(['show_id' => $show->id]);

    $movieItem = RequestItem::factory()->create([
        'request_id' => $request->id,
        'requestable_type' => Movie::class,
        'requestable_id' => $movie->id,
    ]);
    $episodeItem = RequestItem::factory()->create([
        'request_id' => $request->id,
        'requestable_type' => Episode::class,
        'requestable_id' => $episode->id,
    ]);

    Livewire::test(RequestItemsRelationManager::class, [
        'ownerRecord' => $request,
        'pageClass' => ViewRequest::class,
    ])
        ->assertOk()
        ->assertCanSeeTableRecords([$movieItem, $episodeItem])
        ->assertSee('Movie')
        ->assertSee('Episode');
});

it('does not show create action but allows bulk status updates', function () {
    $request = Request::factory()->create();
    $items = RequestItem::factory()->for($request)->count(2)->create();

    Livewire::test(RequestItemsRelationManager::class, [
        'ownerRecord' => $request,
        'pageClass' => ViewRequest::class,
    ])
        ->assertOk()
        ->assertDontSee('New request item')
        ->selectTableRecords($items->pluck('id')->toArray())
        ->assertActionVisible(TestAction::make('markFulfilled')->table()->bulk());
});

it('displays status badges for request items', function () {
    $request = Request::factory()->create();

    $pendingItem = RequestItem::factory()->for($request)->pending()->create();
    $fulfilledItem = RequestItem::factory()->for($request)->fulfilled($this->admin->id)->create();
    $rejectedItem = RequestItem::factory()->for($request)->rejected($this->admin->id)->create();
    $notFoundItem = RequestItem::factory()->for($request)->notFound($this->admin->id)->create();

    Livewire::test(RequestItemsRelationManager::class, [
        'ownerRecord' => $request,
        'pageClass' => ViewRequest::class,
    ])
        ->assertOk()
        ->assertSee('Pending')
        ->assertSee('Fulfilled')
        ->assertSee('Rejected')
        ->assertSee('Not Found');
});

it('shows bulk actions when items are selected', function () {
    $request = Request::factory()->create();
    $items = RequestItem::factory()->for($request)->count(3)->create();

    Livewire::test(RequestItemsRelationManager::class, [
        'ownerRecord' => $request,
        'pageClass' => ViewRequest::class,
    ])
        ->selectTableRecords($items->pluck('id')->toArray())
        ->assertActionVisible(TestAction::make('markFulfilled')->table()->bulk())
        ->assertActionVisible(TestAction::make('markRejected')->table()->bulk())
        ->assertActionVisible(TestAction::make('markNotFound')->table()->bulk())
        ->assertActionVisible(TestAction::make('markPending')->table()->bulk());
});

it('disables selection for items actioned by others', function () {
    $nonAdmin = User::factory()->create();
    $otherUser = User::factory()->create();
    $request = Request::factory()->create();

    $authorizedItem = RequestItem::factory()->for($request)->fulfilled($nonAdmin->id)->create();
    $unauthorizedItem = RequestItem::factory()->for($request)->fulfilled($otherUser->id)->create();

    $this->actingAs($nonAdmin);

    $component = Livewire::test(RequestItemsRelationManager::class, [
        'ownerRecord' => $request,
        'pageClass' => ViewRequest::class,
    ]);

    $table = $component->instance()->getTable();

    expect($table->isRecordSelectable($authorizedItem))->toBeTrue();
    expect($table->isRecordSelectable($unauthorizedItem))->toBeFalse();
});

it('can mark selected items as fulfilled', function () {
    $request = Request::factory()->create();
    $items = RequestItem::factory()->for($request)->count(3)->create();

    Livewire::test(RequestItemsRelationManager::class, [
        'ownerRecord' => $request,
        'pageClass' => ViewRequest::class,
    ])
        ->selectTableRecords($items->take(2)->pluck('id')->toArray())
        ->callAction(TestAction::make('markFulfilled')->table()->bulk())
        ->assertNotified('Items marked as fulfilled');

    $request->refresh();

    expect($items->fresh()->take(2)->every(
        fn ($item) => $item->status === RequestItemStatus::Fulfilled
            && $item->actioned_by === $this->admin->id
    ))->toBeTrue();

    expect($items->fresh()->last()->status)->toBe(RequestItemStatus::Pending);
});

it('can mark selected items as rejected', function () {
    $request = Request::factory()->create();
    $items = RequestItem::factory()->for($request)->count(3)->create();

    Livewire::test(RequestItemsRelationManager::class, [
        'ownerRecord' => $request,
        'pageClass' => ViewRequest::class,
    ])
        ->selectTableRecords($items->pluck('id')->toArray())
        ->callAction(TestAction::make('markRejected')->table()->bulk());

    expect($items->fresh()->every(
        fn ($item) => $item->status === RequestItemStatus::Rejected
            && $item->actioned_by === $this->admin->id
    ))->toBeTrue();
});

it('can mark selected items as not found', function () {
    $request = Request::factory()->create();
    $items = RequestItem::factory()->for($request)->count(3)->create();

    Livewire::test(RequestItemsRelationManager::class, [
        'ownerRecord' => $request,
        'pageClass' => ViewRequest::class,
    ])
        ->selectTableRecords($items->pluck('id')->toArray())
        ->callAction(TestAction::make('markNotFound')->table()->bulk());

    expect($items->fresh()->every(
        fn ($item) => $item->status === RequestItemStatus::NotFound
            && $item->actioned_by === $this->admin->id
    ))->toBeTrue();
});

it('can mark selected items as pending', function () {
    $request = Request::factory()->create();
    $items = RequestItem::factory()->for($request)->fulfilled($this->admin->id)->count(3)->create();

    Livewire::test(RequestItemsRelationManager::class, [
        'ownerRecord' => $request,
        'pageClass' => ViewRequest::class,
    ])
        ->selectTableRecords($items->pluck('id')->toArray())
        ->callAction(TestAction::make('markPending')->table()->bulk());

    expect($items->fresh()->every(
        fn ($item) => $item->status === RequestItemStatus::Pending
            && $item->actioned_by === null
            && $item->actioned_at === null
    ))->toBeTrue();
});

it('admin can change status of items actioned by others', function () {
    $otherUser = User::factory()->create();
    $request = Request::factory()->create();
    $items = RequestItem::factory()->for($request)->fulfilled($otherUser->id)->count(2)->create();

    Livewire::test(RequestItemsRelationManager::class, [
        'ownerRecord' => $request,
        'pageClass' => ViewRequest::class,
    ])
        ->selectTableRecords($items->pluck('id')->toArray())
        ->callAction(TestAction::make('markRejected')->table()->bulk());

    expect($items->fresh()->every(
        fn ($item) => $item->status === RequestItemStatus::Rejected
            && $item->actioned_by === $this->admin->id
    ))->toBeTrue();
});

it('non-admin cannot change status of items actioned by others', function () {
    $nonAdmin = User::factory()->create();
    $otherUser = User::factory()->create();
    $request = Request::factory()->create();
    $items = RequestItem::factory()->for($request)->fulfilled($otherUser->id)->count(2)->create();

    $this->actingAs($nonAdmin);

    Livewire::test(RequestItemsRelationManager::class, [
        'ownerRecord' => $request,
        'pageClass' => ViewRequest::class,
    ])
        ->selectTableRecords($items->pluck('id')->toArray())
        ->callAction(TestAction::make('markRejected')->table()->bulk())
        ->assertNotified('Authorization failed');

    expect($items->fresh()->every(
        fn ($item) => $item->status === RequestItemStatus::Fulfilled
    ))->toBeTrue();
});

it('non-admin can change status of items they actioned', function () {
    $nonAdmin = User::factory()->create();
    $request = Request::factory()->create();
    $items = RequestItem::factory()->for($request)->fulfilled($nonAdmin->id)->count(2)->create();

    $this->actingAs($nonAdmin);

    Livewire::test(RequestItemsRelationManager::class, [
        'ownerRecord' => $request,
        'pageClass' => ViewRequest::class,
    ])
        ->selectTableRecords($items->pluck('id')->toArray())
        ->callAction(TestAction::make('markRejected')->table()->bulk());

    expect($items->fresh()->every(
        fn ($item) => $item->status === RequestItemStatus::Rejected
            && $item->actioned_by === $nonAdmin->id
    ))->toBeTrue();
});

it('non-admin can change status of pending items', function () {
    $nonAdmin = User::factory()->create();
    $request = Request::factory()->create();
    $items = RequestItem::factory()->for($request)->pending()->count(2)->create();

    $this->actingAs($nonAdmin);

    Livewire::test(RequestItemsRelationManager::class, [
        'ownerRecord' => $request,
        'pageClass' => ViewRequest::class,
    ])
        ->selectTableRecords($items->pluck('id')->toArray())
        ->callAction(TestAction::make('markFulfilled')->table()->bulk());

    expect($items->fresh()->every(
        fn ($item) => $item->status === RequestItemStatus::Fulfilled
            && $item->actioned_by === $nonAdmin->id
    ))->toBeTrue();
});

it('non-admin cannot reset items actioned by others to pending', function () {
    $nonAdmin = User::factory()->create();
    $otherUser = User::factory()->create();
    $request = Request::factory()->create();
    $items = RequestItem::factory()->for($request)->fulfilled($otherUser->id)->count(2)->create();

    $this->actingAs($nonAdmin);

    Livewire::test(RequestItemsRelationManager::class, [
        'ownerRecord' => $request,
        'pageClass' => ViewRequest::class,
    ])
        ->selectTableRecords($items->pluck('id')->toArray())
        ->callAction(TestAction::make('markPending')->table()->bulk())
        ->assertNotified('Authorization failed');

    expect($items->fresh()->every(
        fn ($item) => $item->status === RequestItemStatus::Fulfilled
    ))->toBeTrue();
});

it('bulk action fails entirely if any item is unauthorized', function () {
    $nonAdmin = User::factory()->create();
    $otherUser = User::factory()->create();
    $request = Request::factory()->create();

    $authorizedItem = RequestItem::factory()->for($request)->fulfilled($nonAdmin->id)->create();
    $unauthorizedItem = RequestItem::factory()->for($request)->fulfilled($otherUser->id)->create();

    $this->actingAs($nonAdmin);

    Livewire::test(RequestItemsRelationManager::class, [
        'ownerRecord' => $request,
        'pageClass' => ViewRequest::class,
    ])
        ->selectTableRecords([$authorizedItem->id, $unauthorizedItem->id])
        ->callAction(TestAction::make('markRejected')->table()->bulk())
        ->assertNotified('Authorization failed');

    // Both items should remain unchanged
    expect($authorizedItem->fresh()->status)->toBe(RequestItemStatus::Fulfilled);
    expect($unauthorizedItem->fresh()->status)->toBe(RequestItemStatus::Fulfilled);
});
