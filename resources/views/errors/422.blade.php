@extends('errors.layout')
@section('code', '422')
@section('message', 'Yêu cầu chưa thể thực hiện.')
@section('hint')
    @if(request()->routeIs('articles.builder'))
        Yêu cầu cấu hình của bài viết chưa hợp lệ. Vui lòng quay lại Góc công nghệ.
    @elseif(request()->routeIs('cart.edit'))
        Giỏ mua lẻ không đại diện một phương án Builder.
    @else
        Hãy quay lại trang trước, kiểm tra thông tin và thử lại.
    @endif
@endsection
