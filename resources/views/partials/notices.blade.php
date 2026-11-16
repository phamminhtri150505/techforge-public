@if (session('notice'))
    <div class="alert alert-light border mt-3" role="status">
        {{ session('notice') }}</div>
@endif
@if ($errors->any())
    <div class="alert alert-danger mt-3" role="alert"><strong>Vui lòng kiểm tra lại.</strong>
        <ul class="mb-0 mt-2">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
