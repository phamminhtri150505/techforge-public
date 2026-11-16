<h1>Bản tổng hợp tin TECHFORGE</h1>
<p>Đã lưu {{ $digest->item_count }} tin mới trong Quản trị. Mỗi nguồn có tối đa ba mục trong bản tổng hợp.</p>
<ul>
    @foreach($digest->entries as $entry)
        <li>
            <strong>{{ $entry['source'] }}</strong> · <a href="{{ $entry['url'] }}">{{ $entry['title'] }}</a>
            @if($entry['summary'])<p>{{ $entry['summary'] }}</p>@endif
        </li>
    @endforeach
</ul>
