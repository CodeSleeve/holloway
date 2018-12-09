### Custom Many Relationship Example

```php
$this->customMany('stickyNotes', function ($query, Collection $clientServices) {
    return $query->from('sticky_notes')
        ->select('sticky_notes.*', 'client_service_sticky_notes.client_service_id')
        ->join('client_service_sticky_notes', 'sticky_notes.id', '=', 'client_service_sticky_notes.sticky_note_id')
        ->whereIn('client_service_sticky_notes.client_service_id', $clientServices->pluck('id'))
        ->get();
}, function (stdClass $clientService, stdClass $stickyNote) {
    return $clientService->id == $stickyNote->client_service_id;
}, StickyNote::class);
```