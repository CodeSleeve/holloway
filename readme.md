
# Holloway 

<!-- ![image of a Holloway in nature](./annie-spratt-holloway-unsplash-small.jpg?raw=true) -->
<img
  src="./annie-spratt-holloway-unsplash-small.jpg"
  alt="image of a Holloway in nature"
  title="A Holloway in nature"
  style="display: block; margin: 0 auto; max-width: 320px">

Holloway is a loose implementation of the datamapper pattern (Fowler). It's an ORM built on top of the `illuminate/database` package, which is also the same package that powers Laravel's implemenation of Active Record: Eloquent. Because of this, a Holloway mapper can be used used to retrieve results from a database similar to how an Eloquent model is used. However, the Entities returned from it are completely decoupled from the underlying database and can be designed to work however you wish them to.

**Why use this package?**

 Some day you may find yourself needing unbreakable domain objects (entities that are never allowed to exist in an invalid state) in your Laravel app.  If you wish to query these using the same query builder syntax you know and love, Holloway may be of use to you.

## Relationships

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