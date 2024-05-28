@extends('NavbarAdmin')
@section('content')
<body>
    <main>
    <div class="col-12 pl-5 pr-5 mb-5 mt-4">
        {{-- handle valide $request --}}
        @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            @foreach ($errors->all() as $error)
            {{ $error }}
            @endforeach
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
        @endif
        {{-- with success --}}
        @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
        @endif
        {{-- with error --}}
        @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            @foreach (session('error') as $error)
            {{ $error }}
            @endforeach
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
        @endif
        {{-- card riwayat saldo --}}
        <div class="card shadow">
            <div class="card-header">
                <h3>Riwayat Penarikan Saldo</h3>
            </div>
            <div class="card-body">
                <table class="table table-bordered table-striped" id="tx">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Tanggal</th>
                            <th>Jenis</th>
                            <th>Jumlah</th>
                            <th>Status</th>
                            <th>Aksi</th>

                        </tr>
                    </thead>
                    <tbody>

                        @foreach($riwayat_saldo as $saldo)
                        <tr>
                            <td>{{ $loop->iteration }}</td>
                            <td>{{ $saldo->created_at }}</td>
                            <td>{{ $saldo->jenis }}</td>
                            <td>Rp{{ number_format($saldo->jumlah, 2, ',', '.') }}</td>
                            <td>{{ $saldo->status }}</td>
                            <td>
                                @if($saldo->status == 'pending')
                                {{-- tombol modal detail penarikan --}}
                                <button type="button" class="btn btn-primary" data-toggle="modal"
                                    data-target="#detail{{ $saldo->id }}">
                                    Detail Penarikan
                                </button>
                                {{-- modal detail penarikan --}}
                                <div class="modal fade bd-example-modal-lg" id="detail{{ $saldo->id }}" tabindex="-1"
                                    role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
                                    <div class="modal-dialog modal-lg" role="document">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title" id="exampleModalLabel">Detail Penarikan Saldo</h5>
                                                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                    <span aria-hidden="true">&times;</span>
                                                </button>
                                            </div>
                                            <div class="modal-body text-left">
                                                <div class="form-group row">
                                                    <label for="staticEmail" class="col-sm-2 col-form-label">Tanggal</label>
                                                    <div class="col-sm-10">
                                                        <input type="text" readonly class="form-control-plaintext"
                                                            id="staticEmail" value="{{ $saldo->created_at }}">
                                                    </div>
                                                </div>
                                                {{-- jumlah, nama, bank, norek --}}
                                                <div class="form-group row">
                                                    <label for="staticEmail" class="col-sm-2 col-form-label">Jumlah</label>
                                                    <div class="col-sm-10">
                                                        <input type="text" readonly class="form-control-plaintext"
                                                            id="staticEmail"
                                                            value="Rp{{ number_format($saldo->jumlah, 2, ',', '.') }}">
                                                    </div>
                                                </div>
                                                <div class="form-group row">
                                                    <label for="staticEmail" class="col-sm-2 col-form-label">Nama</label>
                                                    <div class="col-sm-10">
                                                        <input type="text" readonly class="form-control-plaintext"
                                                            id="staticEmail" value="{{ $saldo->nama }}">
                                                    </div>
                                                </div>
                                                <div class="form-group row">
                                                    <label for="staticEmail" class="col-sm-2 col-form-label">Bank</label>
                                                    <div class="col-sm-10">
                                                        <input type="text" readonly class="form-control-plaintext"
                                                            id="staticEmail" value="{{ $saldo->bank }}">
                                                    </div>
                                                </div>
                                                <div class="form-group row">
                                                    <label for="staticEmail" class="col-sm-2 col-form-label">No Rekening</label>
                                                    <div class="col-sm-10">
                                                        <input type="text" readonly class="form-control-plaintext"
                                                            id="staticEmail" value="{{ $saldo->norek }}">
                                                    </div>
                                                </div>
                                                {{-- acc / tolak --}}
                                                <div class="form-group row">
                                                    <a href="{{ route('admin.penarikan_saldo.accept', $saldo->id) }}"
                                                        class="btn btn-success">ACC</a>
                                                    <a href="{{ route('admin.penarikan_saldo.reject', $saldo->id) }}"
                                                        class="btn btn-danger">Tolak</a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    </main>
    <script>
        $(document).ready(function() {
            $('#tx').DataTable([{
                @if($riwayat_saldo->count()<1)
                //gabungkan semua column dan berikan pesan
                "language": {
                    "zeroRecords": "Tidak ada data"
                }
                @endif
            }]);

        });
    </script>
</body>
@endsection

