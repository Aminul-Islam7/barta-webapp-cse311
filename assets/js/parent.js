document.addEventListener("DOMContentLoaded", () => {
//CHILD MESSAGE LIMIT DETAILS
    document.querySelectorAll('.view-details-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const childId = this.dataset.childId;
            const detailsContainer = document.getElementById('child-details-' + childId);

            if (detailsContainer.style.display === 'block') {
                detailsContainer.style.display = 'none';
                return;
            }

            fetch(`./parent/get_remaining_limit_stat.php?child_id=${childId}`)
                .then(res => res.json())
                .then(data => {
                    if (data.error) {
                        detailsContainer.innerHTML =
                            `<span style="color:red;">${data.error}</span>`;
                    } else {
                        detailsContainer.innerHTML = `
                            <div class="child-card__stat">
                                <span class="form-label" style="margin: 0rem;">Remaining Messages:</span>
                                <span class="child-card__stat-value">${data.remaining}</span>
                            </div>
                        `;
                    }
                    detailsContainer.style.display = 'block';
                })
                .catch(err => {
                    detailsContainer.innerHTML =
                        `<span style="color:red;">Error fetching data</span>`;
                    detailsContainer.style.display = 'block';
                });
        });
    });

//SETTINGS SIDE PANEL
    const openBtn = document.querySelector(".p-dashboard-parent__settings-btn");
    const panel = document.getElementById("settingsPanel");
    const closeBtn = document.getElementById("closeSettings");

    if (openBtn && panel && closeBtn) {
        openBtn.addEventListener("click", (e) => {
            e.preventDefault();
            panel.classList.add("open");
        });

        closeBtn.addEventListener("click", () => {
            panel.classList.remove("open");
        });
    }
    
// AJAX FORM SUBMISSION FOR DAILY LIMIT UPDATES
document.querySelectorAll('form[action*="update_daily_limit"]').forEach(form => {
    form.addEventListener('submit', function(e) {
        e.preventDefault(); // STOP PAGE RELOAD!
        
        const formData = new FormData(this);
        const submitBtn = this.querySelector('button[type="submit"]');
        const originalText = submitBtn.textContent;
        
        // Show loading
        submitBtn.textContent = 'Updating...';
        submitBtn.disabled = true;
        
        fetch('parent/update_daily_limit.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                // Success - show checkmark
                submitBtn.textContent = '✓ Updated';
                submitBtn.style.backgroundColor = '#28a745';
                
                // Reset button after 2 seconds
                setTimeout(() => {
                    submitBtn.textContent = originalText;
                    submitBtn.style.backgroundColor = '';
                    submitBtn.disabled = false;
                }, 2000);
            } else {
                // Error
                submitBtn.textContent = '✗ Failed';
                submitBtn.style.backgroundColor = '#dc3545';
                alert(data.error || 'Update failed');
                
                setTimeout(() => {
                    submitBtn.textContent = originalText;
                    submitBtn.style.backgroundColor = '';
                    submitBtn.disabled = false;
                }, 2000);
            }
        })
        .catch(err => {
            console.error('Update error:', err);
            submitBtn.textContent = '✗ Error';
            submitBtn.style.backgroundColor = '#dc3545';
            
            setTimeout(() => {
                submitBtn.textContent = originalText;
                submitBtn.style.backgroundColor = '';
                submitBtn.disabled = false;
            }, 2000);
        });
    });
});

//FETCH AND UPDATE SENT/RECEIVED COUNTS
    document.querySelectorAll(".sent-count").forEach(el => {
        const childId = el.dataset.childId;
        fetch(`parent/get_remaining_limit_stat.php?child_id=${childId}`)
            .then(res => res.json())
            .then(data => {
                if (!data.error) {
                    // Update sent count
                    el.textContent = data.sent_today;
                    // Update received count
                    document.querySelector(
                        `.received-count[data-child-id="${childId}"]`
                    ).textContent = data.received_today;
                }}
            );
        });
 });