$(document).ready(function() {
    // ---------- Sidebar toggle (mobile/tablet) ----------
    $('#sidebarToggle').on('click', function() {
        $('body').toggleClass('show-sidebar');
    });
    $('#sidebarOverlay').on('click', function() {
        $('body').removeClass('show-sidebar');
    });
    // Close sidebar when a menu link is clicked (on small screens)
    $('.sidebar .nav-link').on('click', function() {
        if ($(window).width() < 992) {
            $('body').removeClass('show-sidebar');
        }
    });

    // ---------- DataTables (safe re-initialisation) ----------
    $('.datatable').each(function() {
        if ($.fn.DataTable.isDataTable(this)) {
            $(this).DataTable().destroy();
        }
        $(this).DataTable({
            responsive: true,
            language: { search: "Filter:" }
        });
    });

    // ---------- Notification polling with sound ----------
let prevUnreadCount = 0;

function fetchNotifications() {
    $.ajax({
        url: '../ajax/notifications.php',
        method: 'GET',
        dataType: 'json',
        success: function(data) {
            var count = data.count;
            var $badge = $('#notifCount');
            var $list = $('#notifList');

            if (count > 0) {
                $badge.text(count).show();
                var html = '';
                $.each(data.notifications, function(i, n) {
                    html += '<li><a class="dropdown-item" href="#" data-id="'+n.id+'">' +
                            '<small class="text-muted">'+n.created_at+'</small><br>'+n.message+'</a></li>';
                });
                html += '<li><hr class="dropdown-divider"></li><li><a class="dropdown-item text-center mark-all-read" href="#">Mark all as read</a></li>';
                $list.html(html);

                // Play sound if the count increased (new notification arrived)
                if (count > prevUnreadCount) {
                    try {
                        var audio = new Audio('../assets/sounds/software-interface-back-2575.wav');
                        audio.play();
                    } catch (e) {
                        // Sound file might be missing – ignore
                    }
                }
            } else {
                $badge.hide();
                $list.html('<li><span class="dropdown-item-text text-muted">No new notifications</span></li>');
            }

            prevUnreadCount = count;
        }
    });
}

// Mark a notification as read when clicked
$(document).on('click', '#notifList a[data-id]', function(e) {
    e.preventDefault();
    var id = $(this).data('id');
    $.post('../ajax/notifications.php', { mark_read: 1, id: id }, function() {
        fetchNotifications();
    });
});

// Mark all as read
$(document).on('click', '.mark-all-read', function(e) {
    e.preventDefault();
    $.post('../ajax/notifications.php', { mark_all_read: 1 }, function() {
        fetchNotifications();
    });
});

// Poll every 15 seconds
setInterval(fetchNotifications, 15000);
fetchNotifications();

    // ---------- SweetAlert for delete confirmations ----------
    $('.delete-confirm').click(function(e) {
        e.preventDefault();
        var form = $(this).closest('form');
        Swal.fire({
            title: 'Are you sure?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#0FA958',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Yes, delete'
        }).then(function(result) {
            if (result.isConfirmed) {
                form.submit();
            }
        });
    });

    // ---------- Barber Traffic modal (online booking) ----------
    // Only if the traffic modal exists on the page
    if ($('#trafficModal').length) {
        $('#trafficModal').on('show.bs.modal', function() {
            $.getJSON('../ajax/barber_traffic.php', function(data) {
                var html = '';
                if (!data || data.length === 0) {
                    html = '<p class="text-center">No barbers available.</p>';
                } else {
                    data.forEach(function(barber) {
                        var totalLoad = parseInt(barber.appointments) + parseInt(barber.queue);
                        var statusClass = 'traffic-free';
                        var statusText = 'Free';
                        if (totalLoad >= 4) {
                            statusClass = 'traffic-busy';
                            statusText = 'Busy';
                        } else if (totalLoad >= 1) {
                            statusClass = 'traffic-moderate';
                            statusText = 'Moderate';
                        }
                        html += '<div class="d-flex align-items-center mb-3 p-2 rounded" style="background:rgba(255,255,255,0.05);">' +
                            '<img src="../uploads/barbers/' + (barber.photo || 'default.jpg') + '" width="40" height="40" class="rounded-circle me-3" ' +
                            'onerror="this.src=\'../assets/images/default-avatar.png\'" style="object-fit:cover;">' +
                            '<div class="flex-grow-1">' +
                            '<strong>' + barber.full_name + '</strong><br>' +
                            '<small class="text-muted">Appts: ' + barber.appointments + ' | Queue: ' + barber.queue + '</small>' +
                            '</div>' +
                            '<span class="fw-bold ' + statusClass + '">' + statusText + '</span>' +
                        '</div>';
                    });
                }
                $('#trafficContent').html(html);
            });
        });
    }
});