<div class="driver-row-details p-3">
    <div class="row g-3">
        <div class="col-lg-7">
            <h6 class="mb-2">{{ __('message.services') }} <span class="text-muted">({{ $services->count() }})</span></h6>
            @forelse($services as $service)
                <span class="badge badge-light-{{ $service['color'] }} text-{{ $service['color'] }} me-1 mb-1">{{ $service['label'] }}</span>
            @empty
                <span class="text-muted">-</span>
            @endforelse
        </div>
    </div>
</div>
