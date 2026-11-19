@foreach(\App\Models\Label::GROUPS as $kind => $heading)
    @php($group = $record->labels->where('kind', $kind)->sortBy('name'))
    @if($group->isNotEmpty())
        <div class="offer-labels"><strong>{{ $heading }}</strong>
            <ul>@foreach($group as $label)<li>{{ $label->name }}</li>@endforeach</ul>
        </div>
    @endif
@endforeach
