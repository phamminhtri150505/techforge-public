<footer class="site-footer">
    <div class="container footer-grid">
        <div><a class="brand" href="{{ route('home') }}">TECHFORGE<span class="brand-period">.</span></a><p>{{ request()->routeIs('home') ? 'Học để hiểu. Hiểu để chọn. Chọn để xây dựng.' : 'Hiểu công nghệ. Tạo lựa chọn của bạn.' }}</p></div>
        <nav aria-label="Khám phá thêm"><a href="{{ route('articles.index') }}">Góc công nghệ</a><a href="{{ route('products.index') }}">Danh mục linh kiện</a>@if(request()->routeIs('home'))<a href="{{ route('builder.index') }}">PC Builder</a>@endif<a href="{{ route('reference-builds.index') }}">Cấu hình tham khảo</a></nav>
        <p class="footer-note">Nguồn và loại giá được ghi ở từng sản phẩm.<br>Đặt hàng mô phỏng, không thu tiền hay giao hàng.</p>
    </div>
</footer>
