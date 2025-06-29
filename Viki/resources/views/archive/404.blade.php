@extends('layouts.backend', [ 'type' => 'presence' ])

@section('content')
    <div class="container-fluid">
        <div class="row main-content">
             @include('service::sidebar')
             <div class="col-sm-12 col-md-12 col-lg-12 col-xl-12 presence-content">
                <div class="card row">
                    <div class="card-header">Няма архиви.</div>
                </div>
            </div>
        </div>
    </div>
@endsection
<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/v/dt/jszip-2.5.0/dt-1.10.21/af-2.3.5/b-1.6.2/b-colvis-1.6.2/b-flash-1.6.2/b-html5-1.6.2/b-print-1.6.2/cr-1.5.2/fc-3.3.1/fh-3.1.7/kt-2.5.2/r-2.2.4/rg-1.1.2/rr-1.2.7/sc-2.0.2/sp-1.1.0/sl-1.3.1/datatables.min.css"/>
<link rel="stylesheet" href="https://cdn.datatables.net/1.10.21/css/jquery.dataTables.min.css">

<script
        src="https://code.jquery.com/jquery-3.5.1.min.js"
        integrity="sha256-9/aliU8dGd2tb6OSsuzixeV4y/faTqgFtohetphbbj0="
        crossorigin="anonymous"></script>
<script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.36/pdfmake.min.js" defer></script>
<script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.36/vfs_fonts.js" defer></script>
<script type="text/javascript" src="https://cdn.datatables.net/v/dt/jszip-2.5.0/dt-1.10.21/af-2.3.5/b-1.6.2/b-colvis-1.6.2/b-flash-1.6.2/b-html5-1.6.2/b-print-1.6.2/cr-1.5.2/fc-3.3.1/kt-2.5.2/r-2.2.4/rg-1.1.2/rr-1.2.7/sc-2.0.2/sp-1.1.0/sl-1.3.1/datatables.min.js" defer></script>

<style>
    .noselect {
        -webkit-touch-callout: none; /* iOS Safari */
        -webkit-user-select: none; /* Safari */
        -khtml-user-select: none; /* Konqueror HTML */
        -moz-user-select: none; /* Old versions of Firefox */
        -ms-user-select: none; /* Internet Explorer/Edge */
        user-select: none; /* Non-prefixed version, currently
                                  supported by Chrome, Edge, Opera and Firefox */
    }
    table{
        margin: 0 auto;
        clear: both;
        border-collapse: collapse;
        table-layout: fixed;
        word-wrap:break-word;
    }
    div.presence-table div.DTFC_RightBodyLiner,
    div.presence-table div.DTFC_LeftBodyLiner {
        overflow: hidden;
    }
    button.export_excel,
    button.ctrl_btn {
        padding: .5rem 4rem;
        background: #9fc5f8;
        border: none;
        box-shadow: 4px 4px 1px -2px black;
        border: 2px solid black;
        border-radius: 0;
        transition: .3s;
    }
    button.ctrl_btn {
        background: #e06566;
        padding: .5rem;
    }
    button.dt-button.export_excel:hover:not(.disabled),
    div.dt-button.export_excel:hover:not(.disabled),
    a.dt-button.export_excel:hover:not(.disabled) {
        background: #5e9ef5;
        border: 2px solid black;
        transform: scale(1.05);
    }
    button.dt-button.ctrl_btn:hover:not(.disabled),
    div.dt-button.ctrl_btn:hover:not(.disabled),
    a.dt-button.ctrl_btn:hover:not(.disabled) {
        background: #e83e40;
        border: 2px solid black;
        transform: scale(1.05);
    }
    .presence-table table th,
    .presence-table table td {
        text-align: center;
    }
    .presence-table div.dt-buttons {
        float: right !important;
        margin-top: 10px;
    }
    .presence-table .dataTables_wrapper .dataTables_paginate {
        float: left !important;
    }
    table .profession th:first-child {
        text-overflow: ellipsis;
        overflow: hidden;
        white-space: nowrap;
    }
    table .profession th:hover {
        white-space: normal;
    }
    .presence-table table tr td.day-off,
    .presence-table table tr th.day-off {
        background: #e06566;
        color: white;
    }
    .presence-table table tr td.unsaved-changes,
    .presence-table table tr th.unsaved-changes {
        background: yellow;
        color: black;
    }

    .presence-table table tr td.unapproved,
    .presence-table table tr th.unapproved {
        background: dimgrey;
        color: white;
    }

    .presence-table table tr td.vacation,
    .presence-table table tr th.vacation {
        background-color: skyblue;
        color: black;
    }

    .presence-table table tr td.vacation.day-off,
    .presence-table table tr th.vacation.day-off {
        background-color: hotpink;
        color: black;
    }
</style>