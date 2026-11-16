@auth
    <a href="{{ route('configurations.index') }}">Cấu hình đã lưu</a>
    <a href="{{ route('orders.index') }}">Đơn hàng</a>
    @can('admin')<a href="{{ route('admin.index') }}">Quản trị</a>@endcan
    <form method="post" action="{{ route('logout') }}">@csrf<button class="btn btn-outline-dark btn-sm">Đăng xuất</button></form>
@else
    <a class="btn btn-dark btn-sm" href="{{ route('login') }}">Đăng nhập <span aria-hidden="true">↗</span></a>
@endauth
