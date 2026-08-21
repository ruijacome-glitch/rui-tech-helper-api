@extends('admin.layout')

@section('title', 'Conteúdo do site')

@section('content')
    <h1>Conteúdo do site</h1>

    @if (session('status'))
        <div class="admin-flash">{{ session('status') }}</div>
    @endif

    <form method="POST" action="{{ route('admin.conteudo.update') }}">
        @csrf
        @method('PUT')

        <div class="admin-card">
            <h2>Contacto</h2>
            <div class="admin-field">
                <label for="contacto_telefone">Telefone</label>
                <input type="text" id="contacto_telefone" name="contacto[telefone]" value="{{ old('contacto.telefone', $contacto['telefone']) }}">
                @error('contacto.telefone')<div class="admin-field-error">{{ $message }}</div>@enderror
            </div>
            <div class="admin-field">
                <label for="contacto_email">Email</label>
                <input type="email" id="contacto_email" name="contacto[email]" value="{{ old('contacto.email', $contacto['email']) }}">
                @error('contacto.email')<div class="admin-field-error">{{ $message }}</div>@enderror
            </div>
            <div class="admin-field">
                <label for="contacto_whatsapp">WhatsApp</label>
                <input type="text" id="contacto_whatsapp" name="contacto[whatsapp]" value="{{ old('contacto.whatsapp', $contacto['whatsapp']) }}">
                @error('contacto.whatsapp')<div class="admin-field-error">{{ $message }}</div>@enderror
            </div>
        </div>

        <div class="admin-card">
            <h2>Testemunho</h2>
            <div class="admin-field">
                <label for="testemunho_citacao">Citação</label>
                <textarea id="testemunho_citacao" name="testemunho[citacao]">{{ old('testemunho.citacao', $testemunho['citacao']) }}</textarea>
                @error('testemunho.citacao')<div class="admin-field-error">{{ $message }}</div>@enderror
            </div>
            <div class="admin-field">
                <label for="testemunho_atribuicao">Atribuição</label>
                <input type="text" id="testemunho_atribuicao" name="testemunho[atribuicao]" value="{{ old('testemunho.atribuicao', $testemunho['atribuicao']) }}">
                @error('testemunho.atribuicao')<div class="admin-field-error">{{ $message }}</div>@enderror
            </div>
        </div>

        <div class="admin-card">
            <h2>Preços</h2>
            <table class="admin-precos-table">
                <thead>
                    <tr>
                        <th>Secção</th>
                        <th>Serviço</th>
                        <th>Título</th>
                        <th>Valor</th>
                        <th>Nota</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($precos as $preco)
                        <tr>
                            <td>{{ $preco->secao->value }}</td>
                            <td>{{ $preco->servico }}</td>
                            <td>
                                <input type="text" name="precos[{{ $preco->id }}][titulo]" value="{{ old('precos.'.$preco->id.'.titulo', $preco->titulo) }}">
                                @error('precos.'.$preco->id.'.titulo')<div class="admin-field-error">{{ $message }}</div>@enderror
                            </td>
                            <td>
                                <input type="text" name="precos[{{ $preco->id }}][valor]" value="{{ old('precos.'.$preco->id.'.valor', $preco->valor) }}">
                                @error('precos.'.$preco->id.'.valor')<div class="admin-field-error">{{ $message }}</div>@enderror
                            </td>
                            <td>
                                <input type="text" name="precos[{{ $preco->id }}][nota]" value="{{ old('precos.'.$preco->id.'.nota', $preco->nota) }}">
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <button type="submit" class="admin-btn">Guardar</button>
    </form>
@endsection
