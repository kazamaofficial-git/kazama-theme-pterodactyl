@extends('layouts.admin')
@section('title') Pengaturan Kazama @endsection
@section('content-header')
    <h1>Pengaturan Kazama<small>Kontak admin &amp; masa aktif server.</small></h1>
    <ol class="breadcrumb"><li><a href="{{ route('admin.index') }}">Admin</a></li><li class="active">Pengaturan Kazama</li></ol>
@endsection
@section('content')
<div class="row"><div class="col-md-6"><div class="box box-primary">
  <form action="{{ route('kazama.admin.settings.save') }}" method="POST">{{ csrf_field() }}
  <div class="box-body">
    <div class="form-group"><label>Link Telegram admin (pembelian server)</label><input type="url" name="contact_telegram" value="{{ $s['telegram'] }}" class="form-control" placeholder="https://t.me/DhilzzKZ"></div>
    <div class="form-group"><label>Link WhatsApp admin</label><input type="url" name="contact_whatsapp" value="{{ $s['whatsapp'] }}" class="form-control" placeholder="https://wa.me/62812xxxxxxx"></div>
    <div class="row">
      <div class="form-group col-sm-6"><label>Masa aktif server baru (hari)</label><input type="number" name="active_days" value="{{ $s['active_days'] }}" class="form-control"></div>
      <div class="form-group col-sm-6"><label>Masa tenggang sebelum dihapus (hari)</label><input type="number" name="grace_days" value="{{ $s['grace_days'] }}" class="form-control"></div>
    </div>
  </div>
  <div class="box-footer"><button class="btn btn-primary pull-right">Simpan</button></div>
  </form>
</div></div></div>
@endsection
