<?php
require '../init.php';
// echo "Welcome, " . htmlspecialchars($_SESSION['username']);


include 'header.php';
include 'navigation.php';
?>
<script src="https://js.pusher.com/8.2/pusher.min.js"></script>
  
<style>
    @media (max-width: 768px) {
        table.dataTable td {
            white-space: normal !important;
        }
    }

    .dataTables_wrapper .dataTables_length {
        float: right;
        margin-right: 10px;
    }

    .dataTables_wrapper .dataTables_filter {
        float: left;
        margin-left: 10px;
    }


    .dataTables_info {
        margin-left: 10px;
    }
</style>
<!-- <a href="logout.php">Logout</a> -->
<!-- [ Main Content ] start -->
<div class="pc-container">
    <div class="pc-content">
        <!-- [ breadcrumb ] start -->
        <div class="page-header">
            <div class="page-block">
                <div class="row align-items-center">

                    <div class="col-md-12">
                        <div class="page-header-title">
                            <h2 class="mb-0">Dashboard</h2>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- [ breadcrumb ] end -->
        <!-- [ Main Content ] start -->
        <div class="row">
            <!-- [ sample-page ] start -->
            <div class="col-sm-12">
                <div class="card shadow table-card">
                    <div class="card-body mt-3">
                        <table class="table-stripped display responsive nowrap table-striped p-1" id="suppliersTable" style="width:100%">
                            <thead>
                                <tr>
                                    <!--<th style="width: 20.127118644067796%;">Aging</th>-->
                                    <th style="width: 20.127118644067796%;">Dealer</th>
                                    <th style="width: 4.872881355932203%;">PR Number</th>
                                    <th style="width: 34.32203389830508%; ">PR Status</th>
                                    <th style="width: 20.127118644067796%;">Canvass Created</th>
                                    <th style="width: 20.127118644067796%;">Department</th>
                                    <th style="width: 20.127118644067796%;">Action</th>
                                    <!-- <th class="text-end" style="width: 7.203389830508475%;">PRICE</th>
                                    <th class="text-end" style="width: 5.508474576271186%;">QTY</th>
                                    <th style="width: 12.182203389830509%;">STATUS</th>
                                    <th class="text-center" style="width: 15.783898305084746%;">ACTIONS</th> -->
                                </tr>
                            </thead>
                            <!-- Data will be populated here by DataTables -->
                        </table>
                    </div>
                </div>
            </div>
            <!-- [ sample-page ] end -->
        </div>
        <div class="row" hidden>
            <div class="col-md-6 col-xxl-3">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                                <div class="avtar avtar-s bg-light-primary">
                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path opacity="0.4" d="M13 9H7" stroke="#4680FF" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                                        <path
                                            d="M22.0002 10.9702V13.0302C22.0002 13.5802 21.5602 14.0302 21.0002 14.0502H19.0402C17.9602 14.0502 16.9702 13.2602 16.8802 12.1802C16.8202 11.5502 17.0602 10.9602 17.4802 10.5502C17.8502 10.1702 18.3602 9.9502 18.9202 9.9502H21.0002C21.5602 9.9702 22.0002 10.4202 22.0002 10.9702Z"
                                            stroke="#4680FF"
                                            stroke-width="1.5"
                                            stroke-linecap="round"
                                            stroke-linejoin="round" />
                                        <path
                                            d="M17.48 10.55C17.06 10.96 16.82 11.55 16.88 12.18C16.97 13.26 17.96 14.05 19.04 14.05H21V15.5C21 18.5 19 20.5 16 20.5H7C4 20.5 2 18.5 2 15.5V8.5C2 5.78 3.64 3.88 6.19 3.56C6.45 3.52 6.72 3.5 7 3.5H16C16.26 3.5 16.51 3.50999 16.75 3.54999C19.33 3.84999 21 5.76 21 8.5V9.95001H18.92C18.36 9.95001 17.85 10.17 17.48 10.55Z"
                                            stroke="#4680FF"
                                            stroke-width="1.5"
                                            stroke-linecap="round"
                                            stroke-linejoin="round" />
                                    </svg>
                                </div>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h6 class="mb-0">All Earnings</h6>
                            </div>
                        </div>
                        <div class="bg-body p-3 mt-3 rounded">
                            <div class="mt-3 row align-items-center">
                                <div class="col-7">
                                    <div id="all-earnings-graph"></div>
                                </div>
                                <div class="col-5">
                                    <h5 class="mb-1">$3,020</h5>
                                    <p class="text-primary mb-0"><i class="ti ti-arrow-up-right"></i> 30.6%</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-xxl-3">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                                <div class="avtar avtar-s bg-light-warning">
                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path
                                            d="M21 7V17C21 20 19.5 22 16 22H8C4.5 22 3 20 3 17V7C3 4 4.5 2 8 2H16C19.5 2 21 4 21 7Z"
                                            stroke="#E58A00"
                                            stroke-width="1.5"
                                            stroke-miterlimit="10"
                                            stroke-linecap="round"
                                            stroke-linejoin="round" />
                                        <path opacity="0.6" d="M14.5 4.5V6.5C14.5 7.6 15.4 8.5 16.5 8.5H18.5" stroke="#E58A00" stroke-width="1.5" stroke-miterlimit="10" stroke-linecap="round" stroke-linejoin="round" />
                                        <path opacity="0.6" d="M8 13H12" stroke="#E58A00" stroke-width="1.5" stroke-miterlimit="10" stroke-linecap="round" stroke-linejoin="round" />
                                        <path opacity="0.6" d="M8 17H16" stroke="#E58A00" stroke-width="1.5" stroke-miterlimit="10" stroke-linecap="round" stroke-linejoin="round" />
                                    </svg>
                                </div>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h6 class="mb-0">Page Views</h6>
                            </div>
                        </div>
                        <div class="bg-body p-3 mt-3 rounded">
                            <div class="mt-3 row align-items-center">
                                <div class="col-7">
                                    <div id="page-views-graph"></div>
                                </div>
                                <div class="col-5">
                                    <h5 class="mb-1">290K+</h5>
                                    <p class="text-warning mb-0"><i class="ti ti-arrow-up-right"></i> 30.6%</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-xxl-3">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                                <div class="avtar avtar-s bg-light-success">
                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M8 2V5" stroke="#2ca87f" stroke-width="1.5" stroke-miterlimit="10" stroke-linecap="round" stroke-linejoin="round" />
                                        <path d="M16 2V5" stroke="#2ca87f" stroke-width="1.5" stroke-miterlimit="10" stroke-linecap="round" stroke-linejoin="round" />
                                        <path opacity="0.4" d="M3.5 9.08984H20.5" stroke="#2ca87f" stroke-width="1.5" stroke-miterlimit="10" stroke-linecap="round" stroke-linejoin="round" />
                                        <path
                                            d="M21 8.5V17C21 20 19.5 22 16 22H8C4.5 22 3 20 3 17V8.5C3 5.5 4.5 3.5 8 3.5H16C19.5 3.5 21 5.5 21 8.5Z"
                                            stroke="#2ca87f"
                                            stroke-width="1.5"
                                            stroke-miterlimit="10"
                                            stroke-linecap="round"
                                            stroke-linejoin="round" />
                                        <path opacity="0.4" d="M15.6947 13.7002H15.7037" stroke="#2ca87f" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                        <path opacity="0.4" d="M15.6947 16.7002H15.7037" stroke="#2ca87f" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                        <path opacity="0.4" d="M11.9955 13.7002H12.0045" stroke="#2ca87f" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                        <path opacity="0.4" d="M11.9955 16.7002H12.0045" stroke="#2ca87f" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                        <path opacity="0.4" d="M8.29431 13.7002H8.30329" stroke="#2ca87f" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                        <path opacity="0.4" d="M8.29395 16.7002H8.30293" stroke="#2ca87f" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                    </svg>
                                </div>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h6 class="mb-0">Total Task</h6>
                            </div>
                        </div>
                        <div class="bg-body p-3 mt-3 rounded">
                            <div class="mt-3 row align-items-center">
                                <div class="col-7">
                                    <div id="total-task-graph"></div>
                                </div>
                                <div class="col-5">
                                    <h5 class="mb-1">839</h5>
                                    <p class="text-success mb-0"><i class="ti ti-arrow-up-right"></i> New</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6 col-xxl-3">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                                <div class="avtar avtar-s bg-light-warning">
                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path
                                            d="M12 22C17.5228 22 22 17.5228 22 12C22 6.47715 17.5228 2 12 2C6.47715 2 2 6.47715 2 12C2 17.5228 6.47715 22 12 22Z"
                                            stroke="#DC2626"
                                            stroke-width="1.5"
                                            stroke-miterlimit="10"
                                            stroke-linecap="round"
                                            stroke-linejoin="round" />
                                        <path opacity="0.4" d="M8.4707 10.7402L12.0007 14.2602L15.5307 10.7402" stroke="#DC2626" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                                    </svg>
                                </div>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h6 class="mb-0">Download</h6>
                            </div>
                        </div>
                        <div class="bg-body p-3 mt-3 rounded">
                            <div class="mt-3 row align-items-center">
                                <div class="col-7">
                                    <div id="download-graph"></div>
                                </div>
                                <div class="col-5">
                                    <h5 class="mb-1">2,067</h5>
                                    <p class="text-danger mb-0"><i class="ti ti-arrow-up-right"></i> 30.6%</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-9">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Monthly Revenue</h5>
                    </div>
                    <div class="card-body">
                        <h5 class="text-end my-2">5.44% <span class="badge bg-success">+2.6%</span></h5>
                        <div id="customer-rate-graph"></div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Project - Able Pro</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-4">
                            <p class="mb-2">Release v1.2.0<span class="float-end">70%</span></p>
                            <div class="progress progress-primary" style="height: 8px">
                                <div class="progress-bar" style="width: 70%"></div>
                            </div>
                        </div>
                        <div class="d-grid gap-2">
                            <a href="#" class="btn btn-link-secondary">
                                <div class="d-flex align-items-center">
                                    <div class="flex-shrink-0">
                                        <span class="p-1 d-block bg-warning rounded-circle">
                                            <span class="visually-hidden">New alerts</span>
                                        </span>
                                    </div>
                                    <div class="flex-grow-1 mx-2">
                                        <p class="mb-0 d-grid text-start">
                                            <span class="text-truncate w-100">Horizontal Layout</span>
                                        </p>
                                    </div>
                                    <div class="badge bg-light-secondary f-12"><i class="ti ti-paperclip text-sm"></i> 2</div>
                                </div>
                            </a>
                            <a href="#" class="btn btn-link-secondary">
                                <div class="d-flex align-items-center">
                                    <div class="flex-shrink-0">
                                        <span class="p-1 d-block bg-warning rounded-circle">
                                            <span class="visually-hidden">New alerts</span>
                                        </span>
                                    </div>
                                    <div class="flex-grow-1 mx-2">
                                        <p class="mb-0 d-grid text-start">
                                            <span class="text-truncate w-100">Invoice Generator</span>
                                        </p>
                                    </div>
                                </div>
                            </a>
                            <a href="#" class="btn btn-link-secondary">
                                <div class="d-flex align-items-center">
                                    <div class="flex-shrink-0">
                                        <span class="p-1 d-block bg-warning rounded-circle">
                                            <span class="visually-hidden">New alerts</span>
                                        </span>
                                    </div>
                                    <div class="flex-grow-1 mx-2">
                                        <p class="mb-0 d-grid text-start">
                                            <span class="text-truncate w-100">Package Upgrades</span>
                                        </p>
                                    </div>
                                </div>
                            </a>
                            <a href="#" class="btn btn-link-secondary">
                                <div class="d-flex align-items-center">
                                    <div class="flex-shrink-0">
                                        <span class="p-1 d-block bg-success rounded-circle">
                                            <span class="visually-hidden">New alerts</span>
                                        </span>
                                    </div>
                                    <div class="flex-grow-1 mx-2">
                                        <p class="mb-0 d-grid text-start">
                                            <span class="text-truncate w-100">Figma Auto Layout</span>
                                        </p>
                                    </div>
                                </div>
                            </a>
                        </div>
                        <div class="d-grid mt-3">
                            <button class="btn btn-primary d-flex align-items-center justify-content-center"><i class="ti ti-plus"></i> Add task</button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-9">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between">
                            <h5 class="mb-0">Project overview</h5>
                            <div class="dropdown">
                                <a class="avtar avtar-s btn-link-secondary dropdown-toggle arrow-none" href="#" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                    <i class="ti ti-dots f-18"></i>
                                </a>
                                <div class="dropdown-menu dropdown-menu-end">
                                    <a class="dropdown-item" href="#">Today</a>
                                    <a class="dropdown-item" href="#">Weekly</a>
                                    <a class="dropdown-item" href="#">Monthly</a>
                                </div>
                            </div>
                        </div>
                        <div class="row align-items-center justify-content-center">
                            <div class="col-md-6 col-xl-4">
                                <div class="mt-3 row align-items-center">
                                    <div class="col-6">
                                        <p class="text-muted mb-1">Total Tasks</p>
                                        <h5 class="mb-0">34,686</h5>
                                    </div>
                                    <div class="col-6">
                                        <div id="total-tasks-graph"></div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6 col-xl-4">
                                <div class="mt-3 row align-items-center">
                                    <div class="col-6">
                                        <p class="text-muted mb-1">Pending Tasks</p>
                                        <h5 class="mb-0">3,786</h5>
                                    </div>
                                    <div class="col-6">
                                        <div id="pending-tasks-graph"></div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6 col-xl-4">
                                <div class="mt-3 d-grid">
                                    <button class="btn btn-primary d-flex align-items-center justify-content-center"><i class="ti ti-plus"></i> Add project</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="flex-shrink-0">
                                <div class="avtar avtar-s bg-light-primary">
                                    <i class="ti ti-at f-20"></i>
                                </div>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h6 class="mb-0">Able pro</h6>
                                <small class="text-muted">@ableprodevelop</small>
                            </div>
                            <div class="dropdown">
                                <a class="avtar avtar-s btn-link-secondary dropdown-toggle arrow-none" href="#" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                    <i class="ti ti-dots-vertical f-18"></i>
                                </a>
                                <div class="dropdown-menu dropdown-menu-end">
                                    <a class="dropdown-item" href="#">Today</a>
                                    <a class="dropdown-item" href="#">Weekly</a>
                                    <a class="dropdown-item" href="#">Monthly</a>
                                </div>
                            </div>
                        </div>
                        <div class="d-flex align-items-center justify-content-between mt-4">
                            <div class="user-group able-user-group">
                                <img src="assets/images/user/avatar-1.jpg" alt="user-image" class="avtar" />
                                <img src="assets/images/user/avatar-3.jpg" alt="user-image" class="avtar" />
                                <img src="assets/images/user/avatar-4.jpg" alt="user-image" class="avtar" />
                                <img src="assets/images/user/avatar-5.jpg" alt="user-image" class="avtar" />
                                <span class="avtar bg-light-primary text-primary text-sm">+2</span>
                            </div>
                            <a href="#" class="avtar avtar-s btn btn-primary rounded-circle">
                                <i class="ti ti-plus f-20"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body border-bottom pb-0">
                        <div class="d-flex align-items-center justify-content-between">
                            <h5 class="mb-0">Transactions</h5>
                            <div class="dropdown">
                                <a class="avtar avtar-s btn-link-secondary dropdown-toggle arrow-none" href="#" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                    <i class="ti ti-dots-vertical f-18"></i>
                                </a>
                                <div class="dropdown-menu dropdown-menu-end">
                                    <a class="dropdown-item" href="#">Today</a>
                                    <a class="dropdown-item" href="#">Weekly</a>
                                    <a class="dropdown-item" href="#">Monthly</a>
                                </div>
                            </div>
                        </div>
                        <ul class="nav nav-tabs analytics-tab" id="myTab" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="analytics-tab-1" data-bs-toggle="tab" data-bs-target="#analytics-tab-1-pane" type="button" role="tab" aria-controls="analytics-tab-1-pane" aria-selected="true">
                                    All Transaction
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="analytics-tab-2" data-bs-toggle="tab" data-bs-target="#analytics-tab-2-pane" type="button" role="tab" aria-controls="analytics-tab-2-pane" aria-selected="false">
                                    Success
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="analytics-tab-3" data-bs-toggle="tab" data-bs-target="#analytics-tab-3-pane" type="button" role="tab" aria-controls="analytics-tab-3-pane" aria-selected="false">
                                    Pending
                                </button>
                            </li>
                        </ul>
                    </div>
                    <div class="tab-content" id="myTabContent">
                        <div class="tab-pane fade show active" id="analytics-tab-1-pane" role="tabpanel" aria-labelledby="analytics-tab-1" tabindex="0">
                            <ul class="list-group list-group-flush">
                                <li class="list-group-item">
                                    <div class="d-flex align-items-center">
                                        <div class="flex-shrink-0">
                                            <div class="avtar avtar-s border">AI</div>
                                        </div>
                                        <div class="flex-grow-1 ms-3">
                                            <div class="row g-1">
                                                <div class="col-6">
                                                    <h6 class="mb-0">Apple Inc.</h6>
                                                    <p class="text-muted mb-0"><small>#ABLE-PRO-T00232</small></p>
                                                </div>
                                                <div class="col-6 text-end">
                                                    <h6 class="mb-1">$210,000</h6>
                                                    <p class="text-danger mb-0"><i class="ti ti-arrow-down-left"></i> 10.6%</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </li>
                                <li class="list-group-item">
                                    <div class="d-flex align-items-center">
                                        <div class="flex-shrink-0">
                                            <div class="avtar avtar-s border" data-bs-toggle="tooltip" data-bs-title="10,000 Tracks"><span>SM</span></div>
                                        </div>
                                        <div class="flex-grow-1 ms-3">
                                            <div class="row g-1">
                                                <div class="col-6">
                                                    <h6 class="mb-0">Spotify Music</h6>
                                                    <p class="text-muted mb-0"><small>#ABLE-PRO-T10232</small></p>
                                                </div>
                                                <div class="col-6 text-end">
                                                    <h6 class="mb-1">- 10,000</h6>
                                                    <p class="text-success mb-0"><i class="ti ti-arrow-up-right"></i> 30.6%</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </li>
                                <li class="list-group-item">
                                    <div class="d-flex align-items-center">
                                        <div class="flex-shrink-0">
                                            <div class="avtar avtar-s border bg-light-primary" data-bs-toggle="tooltip" data-bs-title="143 Posts"><span>MD</span></div>
                                        </div>
                                        <div class="flex-grow-1 ms-3">
                                            <div class="row g-1">
                                                <div class="col-6">
                                                    <h6 class="mb-0">Medium</h6>
                                                    <p class="text-muted mb-0"><small>06:30 pm</small></p>
                                                </div>
                                                <div class="col-6 text-end">
                                                    <h6 class="mb-1">-26</h6>
                                                    <p class="text-warning mb-0"><i class="ti ti-arrows-left-right"></i> 5%</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </li>
                                <li class="list-group-item">
                                    <div class="d-flex align-items-center">
                                        <div class="flex-shrink-0">
                                            <div class="avtar avtar-s border" data-bs-toggle="tooltip" data-bs-title="143 Posts"><span>U</span></div>
                                        </div>
                                        <div class="flex-grow-1 ms-3">
                                            <div class="row g-1">
                                                <div class="col-6">
                                                    <h6 class="mb-0">Uber</h6>
                                                    <p class="text-muted mb-0"><small>08:40 pm</small></p>
                                                </div>
                                                <div class="col-6 text-end">
                                                    <h6 class="mb-1">+210,000</h6>
                                                    <p class="text-success mb-0"><i class="ti ti-arrow-up-right"></i> 10.6%</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </li>
                                <li class="list-group-item">
                                    <div class="d-flex align-items-center">
                                        <div class="flex-shrink-0">
                                            <div class="avtar avtar-s border bg-light-warning" data-bs-toggle="tooltip" data-bs-title="143 Posts"><span>OC</span></div>
                                        </div>
                                        <div class="flex-grow-1 ms-3">
                                            <div class="row g-1">
                                                <div class="col-6">
                                                    <h6 class="mb-0">Ola Cabs</h6>
                                                    <p class="text-muted mb-0"><small>07:40 pm</small></p>
                                                </div>
                                                <div class="col-6 text-end">
                                                    <h6 class="mb-1">+210,000</h6>
                                                    <p class="text-success mb-0"><i class="ti ti-arrow-up-right"></i> 10.6%</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </li>
                            </ul>
                        </div>
                        <div class="tab-pane fade" id="analytics-tab-2-pane" role="tabpanel" aria-labelledby="analytics-tab-2" tabindex="0">
                            <ul class="list-group list-group-flush">
                                <li class="list-group-item">
                                    <div class="d-flex align-items-center">
                                        <div class="flex-shrink-0">
                                            <div class="avtar avtar-s border" data-bs-toggle="tooltip" data-bs-title="143 Posts"><span>U</span></div>
                                        </div>
                                        <div class="flex-grow-1 ms-3">
                                            <div class="row g-1">
                                                <div class="col-6">
                                                    <h6 class="mb-0">Uber</h6>
                                                    <p class="text-muted mb-0"><small>08:40 pm</small></p>
                                                </div>
                                                <div class="col-6 text-end">
                                                    <h6 class="mb-1">+210,000</h6>
                                                    <p class="text-success mb-0"><i class="ti ti-arrow-up-right"></i> 10.6%</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </li>
                                <li class="list-group-item">
                                    <div class="d-flex align-items-center">
                                        <div class="flex-shrink-0">
                                            <div class="avtar avtar-s border bg-light-warning" data-bs-toggle="tooltip" data-bs-title="143 Posts"><span>OC</span></div>
                                        </div>
                                        <div class="flex-grow-1 ms-3">
                                            <div class="row g-1">
                                                <div class="col-6">
                                                    <h6 class="mb-0">Ola Cabs</h6>
                                                    <p class="text-muted mb-0"><small>07:40 pm</small></p>
                                                </div>
                                                <div class="col-6 text-end">
                                                    <h6 class="mb-1">+210,000</h6>
                                                    <p class="text-success mb-0"><i class="ti ti-arrow-up-right"></i> 10.6%</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </li>
                                <li class="list-group-item">
                                    <div class="d-flex align-items-center">
                                        <div class="flex-shrink-0">
                                            <div class="avtar avtar-s border">AI</div>
                                        </div>
                                        <div class="flex-grow-1 ms-3">
                                            <div class="row g-1">
                                                <div class="col-6">
                                                    <h6 class="mb-0">Apple Inc.</h6>
                                                    <p class="text-muted mb-0"><small>#ABLE-PRO-T00232</small></p>
                                                </div>
                                                <div class="col-6 text-end">
                                                    <h6 class="mb-1">$210,000</h6>
                                                    <p class="text-danger mb-0"><i class="ti ti-arrow-down-left"></i> 10.6%</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </li>
                                <li class="list-group-item">
                                    <div class="d-flex align-items-center">
                                        <div class="flex-shrink-0">
                                            <div class="avtar avtar-s border" data-bs-toggle="tooltip" data-bs-title="10,000 Tracks"><span>SM</span></div>
                                        </div>
                                        <div class="flex-grow-1 ms-3">
                                            <div class="row g-1">
                                                <div class="col-6">
                                                    <h6 class="mb-0">Spotify Music</h6>
                                                    <p class="text-muted mb-0"><small>#ABLE-PRO-T10232</small></p>
                                                </div>
                                                <div class="col-6 text-end">
                                                    <h6 class="mb-1">- 10,000</h6>
                                                    <p class="text-success mb-0"><i class="ti ti-arrow-up-right"></i> 30.6%</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </li>
                                <li class="list-group-item">
                                    <div class="d-flex align-items-center">
                                        <div class="flex-shrink-0">
                                            <div class="avtar avtar-s border bg-light-primary" data-bs-toggle="tooltip" data-bs-title="143 Posts"><span>MD</span></div>
                                        </div>
                                        <div class="flex-grow-1 ms-3">
                                            <div class="row g-1">
                                                <div class="col-6">
                                                    <h6 class="mb-0">Medium</h6>
                                                    <p class="text-muted mb-0"><small>06:30 pm</small></p>
                                                </div>
                                                <div class="col-6 text-end">
                                                    <h6 class="mb-1">-26</h6>
                                                    <p class="text-warning mb-0"><i class="ti ti-arrows-left-right"></i> 5%</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </li>
                            </ul>
                        </div>
                        <div class="tab-pane fade" id="analytics-tab-3-pane" role="tabpanel" aria-labelledby="analytics-tab-3" tabindex="0">
                            <ul class="list-group list-group-flush">
                                <li class="list-group-item">
                                    <div class="d-flex align-items-center">
                                        <div class="flex-shrink-0">
                                            <div class="avtar avtar-s border" data-bs-toggle="tooltip" data-bs-title="10,000 Tracks"><span>SM</span></div>
                                        </div>
                                        <div class="flex-grow-1 ms-3">
                                            <div class="row g-1">
                                                <div class="col-6">
                                                    <h6 class="mb-0">Spotify Music</h6>
                                                    <p class="text-muted mb-0"><small>#ABLE-PRO-T10232</small></p>
                                                </div>
                                                <div class="col-6 text-end">
                                                    <h6 class="mb-1">- 10,000</h6>
                                                    <p class="text-success mb-0"><i class="ti ti-arrow-up-right"></i> 30.6%</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </li>
                                <li class="list-group-item">
                                    <div class="d-flex align-items-center">
                                        <div class="flex-shrink-0">
                                            <div class="avtar avtar-s border bg-light-primary" data-bs-toggle="tooltip" data-bs-title="143 Posts"><span>MD</span></div>
                                        </div>
                                        <div class="flex-grow-1 ms-3">
                                            <div class="row g-1">
                                                <div class="col-6">
                                                    <h6 class="mb-0">Medium</h6>
                                                    <p class="text-muted mb-0"><small>06:30 pm</small></p>
                                                </div>
                                                <div class="col-6 text-end">
                                                    <h6 class="mb-1">-26</h6>
                                                    <p class="text-warning mb-0"><i class="ti ti-arrows-left-right"></i> 5%</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </li>
                                <li class="list-group-item">
                                    <div class="d-flex align-items-center">
                                        <div class="flex-shrink-0">
                                            <div class="avtar avtar-s border" data-bs-toggle="tooltip" data-bs-title="143 Posts"><span>U</span></div>
                                        </div>
                                        <div class="flex-grow-1 ms-3">
                                            <div class="row g-1">
                                                <div class="col-6">
                                                    <h6 class="mb-0">Uber</h6>
                                                    <p class="text-muted mb-0"><small>08:40 pm</small></p>
                                                </div>
                                                <div class="col-6 text-end">
                                                    <h6 class="mb-1">+210,000</h6>
                                                    <p class="text-success mb-0"><i class="ti ti-arrow-up-right"></i> 10.6%</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </li>
                                <li class="list-group-item">
                                    <div class="d-flex align-items-center">
                                        <div class="flex-shrink-0">
                                            <div class="avtar avtar-s border">AI</div>
                                        </div>
                                        <div class="flex-grow-1 ms-3">
                                            <div class="row g-1">
                                                <div class="col-6">
                                                    <h6 class="mb-0">Apple Inc.</h6>
                                                    <p class="text-muted mb-0"><small>#ABLE-PRO-T00232</small></p>
                                                </div>
                                                <div class="col-6 text-end">
                                                    <h6 class="mb-1">$210,000</h6>
                                                    <p class="text-danger mb-0"><i class="ti ti-arrow-down-left"></i> 10.6%</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </li>
                                <li class="list-group-item">
                                    <div class="d-flex align-items-center">
                                        <div class="flex-shrink-0">
                                            <div class="avtar avtar-s border bg-light-warning" data-bs-toggle="tooltip" data-bs-title="143 Posts"><span>OC</span></div>
                                        </div>
                                        <div class="flex-grow-1 ms-3">
                                            <div class="row g-1">
                                                <div class="col-6">
                                                    <h6 class="mb-0">Ola Cabs</h6>
                                                    <p class="text-muted mb-0"><small>07:40 pm</small></p>
                                                </div>
                                                <div class="col-6 text-end">
                                                    <h6 class="mb-1">+210,000</h6>
                                                    <p class="text-success mb-0"><i class="ti ti-arrow-up-right"></i> 10.6%</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </li>
                            </ul>
                        </div>
                    </div>
                    <div class="card-footer">
                        <div class="row g-2">
                            <div class="col-md-6">
                                <div class="d-grid">
                                    <button class="btn btn-outline-secondary d-grid"><span class="text-truncate w-100">View all Transaction History</span></button>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="d-grid">
                                    <button class="btn btn-primary d-grid"><span class="text-truncate w-100">Create new Transaction</span></button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between">
                            <h5 class="mb-0">Total Income</h5>
                            <div class="dropdown">
                                <a class="avtar avtar-s btn-link-secondary dropdown-toggle arrow-none" href="#" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                    <i class="ti ti-dots-vertical f-18"></i>
                                </a>
                                <div class="dropdown-menu dropdown-menu-end">
                                    <a class="dropdown-item" href="#">Today</a>
                                    <a class="dropdown-item" href="#">Weekly</a>
                                    <a class="dropdown-item" href="#">Monthly</a>
                                </div>
                            </div>
                        </div>
                        <div id="total-income-graph"></div>
                        <div class="row g-3 mt-3">
                            <div class="col-sm-6">
                                <div class="bg-body p-3 rounded">
                                    <div class="d-flex align-items-center mb-2">
                                        <div class="flex-shrink-0">
                                            <span class="p-1 d-block bg-primary rounded-circle">
                                                <span class="visually-hidden">New alerts</span>
                                            </span>
                                        </div>
                                        <div class="flex-grow-1 ms-2">
                                            <p class="mb-0">Income</p>
                                        </div>
                                    </div>
                                    <h6 class="mb-0">
                                        $23,876 <small class="text-muted"><i class="ti ti-chevrons-up"></i> +$763,43</small>
                                    </h6>
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="bg-body p-3 rounded">
                                    <div class="d-flex align-items-center mb-2">
                                        <div class="flex-shrink-0">
                                            <span class="p-1 d-block bg-warning rounded-circle">
                                                <span class="visually-hidden">New alerts</span>
                                            </span>
                                        </div>
                                        <div class="flex-grow-1 ms-2">
                                            <p class="mb-0">Rent</p>
                                        </div>
                                    </div>
                                    <h6 class="mb-0">
                                        $23,876 <small class="text-muted"><i class="ti ti-chevrons-up"></i> +$763,43</small>
                                    </h6>
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="bg-body p-3 rounded">
                                    <div class="d-flex align-items-center mb-2">
                                        <div class="flex-shrink-0">
                                            <span class="p-1 d-block bg-success rounded-circle">
                                                <span class="visually-hidden">New alerts</span>
                                            </span>
                                        </div>
                                        <div class="flex-grow-1 ms-2">
                                            <p class="mb-0">Download</p>
                                        </div>
                                    </div>
                                    <h6 class="mb-0">
                                        $23,876 <small class="text-muted"><i class="ti ti-chevrons-up"></i> +$763,43</small>
                                    </h6>
                                </div>
                            </div>
                            <div class="col-sm-6">
                                <div class="bg-body p-3 rounded">
                                    <div class="d-flex align-items-center mb-2">
                                        <div class="flex-shrink-0">
                                            <span class="p-1 d-block bg-light-primary rounded-circle">
                                                <span class="visually-hidden">New alerts</span>
                                            </span>
                                        </div>
                                        <div class="flex-grow-1 ms-2">
                                            <p class="mb-0">Views</p>
                                        </div>
                                    </div>
                                    <h6 class="mb-0">
                                        $23,876 <small class="text-muted"><i class="ti ti-chevrons-up"></i> +$763,43</small>
                                    </h6>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- [ Main Content ] end -->
    </div>
</div>
<!-- [ Main Content ] end -->
<div class="modal fade" id="pdfModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">PDF Viewer</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center">
                <canvas id="pdfCanvas"></canvas>
                <p>Page <span id="pageNum">1</span> of <span id="pageCount">1</span></p>
            </div>
            <div class="modal-footer justify-content-between">
                <div>
                    <button class="btn btn-secondary" id="prevPage">Previous</button>
                    <button class="btn btn-secondary" id="nextPage">Next</button>
                </div>
                <div>
                    <button class="btn btn-secondary" id="zoomOut">-</button>
                    <button class="btn btn-secondary" id="zoomIn">+</button>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
    $('#suppliersTable').DataTable({
        processing: true,
        serverSide: true,
        responsive: true,
        ajax: 'data/get_purchase_requisition_completed.php',
        order: [[0, 'desc']],
        columns: [
            
            {
                data: 'pr_dealer'
            },
            {
                data: 'pr_number'
            },
            {
                data: 'pr_status',
                render: function(data, type, row) {
                    // Map status to Bootstrap badge
                    let badgeClass = '';
                    let badgeLabel = '';

                    switch (data) {
                        case 'For Canvass':
                            badgeClass = 'danger';
                            badgeLabel = 'For Canvass';
                            break;
                        case 'For Approval':
                            badgeClass = 'primary';
                            badgeLabel = 'For Approval';
                            break;
                        case 'Success':
                            badgeClass = 'success';
                            badgeLabel = 'Success';
                            break;
                        case 'For PO':
                            badgeClass = 'warning';
                            badgeLabel = 'For PO';
                            break;
                        case 'Completed':
                            badgeClass = 'success';
                            badgeLabel = 'Completed';
                            break;
                        default:
                            badgeClass = 'dark';
                            badgeLabel = data;
                    }

                    return `<span class="badge fs-6 bg-light-${badgeClass}">${badgeLabel}</span>`;
                }
            },
            {
                data: 'canvass_created',
                render: function(data) {
                    if (!data || data === '0000-00-00') return 'N/A'; // Handle empty or invalid dates
                    return new Date(data).toLocaleDateString('en-US', {
                        year: 'numeric',
                        month: 'long',
                        day: '2-digit'
                    });
                }
            },
            {
                data: 'pr_department'
            },
            {
                data: null,
                className: 'text-center',
                orderable: false,
                render: function(data, type, row) {
                    const isDisabled = row.pr_status === 'For Canvass';

                    // Option 1: Disable the button
                   

                    // Option 2: Show the active link
                    return `
            <ul class="list-inline me-auto mb-0">
                <li class="list-inline-item" title="View Canvass">
                    <a href="view_purchase_requisition?pr_number=${row.pr_number}" 
                       class="avtar avtar-xs btn-link-success btn-pc-default">
                       <i class="ti ti-eye f-18"></i>
                    </a>
                </li>
            </ul>
            
        `;
                }
            }

        ]
    });
</script>
<script>
    let pdfDoc = null,
        currentPage = 1,
        scale = 1.5,
        canvas = document.getElementById('pdfCanvas'),
        ctx = canvas.getContext('2d'),
        pageNumSpan = document.getElementById('pageNum'),
        pageCountSpan = document.getElementById('pageCount');

    function renderPage(num) {
        pdfDoc.getPage(num).then(page => {
            const viewport = page.getViewport({
                scale: scale
            });
            canvas.height = viewport.height;
            canvas.width = viewport.width;

            const renderContext = {
                canvasContext: ctx,
                viewport: viewport
            };
            page.render(renderContext);

            pageNumSpan.textContent = num;
            pageCountSpan.textContent = pdfDoc.numPages;
        });
    }

    document.getElementById('zoomIn').addEventListener('click', () => {
        scale += 0.2;
        renderPage(currentPage);
    });

    document.getElementById('zoomOut').addEventListener('click', () => {
        if (scale > 0.4) {
            scale -= 0.2;
            renderPage(currentPage);
        }
    });

    document.getElementById('prevPage').addEventListener('click', () => {
        if (currentPage > 1) {
            currentPage--;
            renderPage(currentPage);
        }
    });

    document.getElementById('nextPage').addEventListener('click', () => {
        if (currentPage < pdfDoc.numPages) {
            currentPage++;
            renderPage(currentPage);
        }
    });

    function viewUploadedPDF(filePath) {
        pdfjsLib.getDocument(filePath).promise.then(pdf => {
            pdfDoc = pdf;
            currentPage = 1;
            renderPage(currentPage);

            const modal = new bootstrap.Modal(document.getElementById('pdfModal'));
            modal.show();
        }).catch(error => {
            console.error('PDF load error:', error);
            alert('Failed to load PDF.');
        });
    }

    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('.view-pdf-btn').forEach(button => {
            button.addEventListener('click', function() {
                const prNumber = this.getAttribute('data-pr-number');

                // Adjust this path to match where your PDFs are stored
                const pdfPath = `data/${prNumber}`;

                viewUploadedPDF(pdfPath);
            });
        });
    });
</script>
<?php
include 'footer.php';
?>