<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SaaS Booking Portal</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen py-10 px-4">
    <div class="max-w-2xl mx-auto bg-white p-8 rounded-xl shadow-lg">
        <h1 class="text-3xl font-extrabold text-gray-900 mb-2">Book Your Appointment</h1>
        <p class="text-gray-500 mb-6">Choose a barbershop, select your service, and pick an available timeslot.</p>
        
        <div id="alertBox" class="hidden mb-6 p-4 rounded-lg text-sm font-medium"></div>

        <form id="bookingForm" class="space-y-6">
           <div>
	    <label class="block text-sm font-semibold text-gray-700 mb-2">Select Barbershop</label>
	    <select id="tenant_id" class="w-full border-gray-300 rounded-lg shadow-sm p-3 border focus:ring-2 focus:ring-blue-500" required>
	        <option value="">-- Choose a Location --</option>
	        @foreach($tenants as $tenant)
	            <option value="{{ $tenant->id }}">{{ $tenant->name }}</option>
	        @endforeach
	    </select>
  	   </div>

            <!-- 2. Select Service -->
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Select Service</label>
                <select id="service_id" class="w-full border-gray-300 rounded-lg shadow-sm p-3 border focus:ring-2 focus:ring-blue-500" required disabled>
                    <option value="">-- First choose a barbershop --</option>
                </select>
            </div>

            <!-- 3. Select Date -->
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Select Date (Mon–Sat)</label>
                <input type="date" id="booking_date" class="w-full border-gray-300 rounded-lg shadow-sm p-3 border focus:ring-2 focus:ring-blue-500" required>
                <p class="text-xs text-gray-400 mt-1">Note: Sundays are closed. Working hours are 09:00 - 21:00.</p>
            </div>

            <!-- 4. Timeslots Grid -->
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Available Timeslots</label>
                <div id="slotsContainer" class="grid grid-cols-3 gap-3 p-4 bg-gray-50 rounded-lg border border-gray-200 min-h-[100px] flex items-center justify-center text-gray-400">
                    Select a barbershop and date to view slots.
                </div>
                <input type="hidden" id="selected_start_time" required>
            </div>

            <button type="submit" id="submitBtn" class="w-full bg-blue-600 text-white p-3 rounded-lg font-bold hover:bg-blue-700 transition shadow-md disabled:opacity-50" disabled>
                Confirm Appointment
            </button>
        </form>
    </div>

    <script>
        const tenantSelect = document.getElementById('tenant_id');
        const serviceSelect = document.getElementById('service_id');
        const dateInput = document.getElementById('booking_date');
        const slotsContainer = document.getElementById('slotsContainer');
        const submitBtn = document.getElementById('submitBtn');
        const hiddenStartTime = document.getElementById('selected_start_time');
        const alertBox = document.getElementById('alertBox');

        let tenantsData = @json($tenantsWithServices);
        let selectedTime = null;

        // When tenant changes, update available services
        tenantSelect.addEventListener('change', function() {
            const tenantId = this.value;
            serviceSelect.innerHTML = '<option value="">-- Select Service --</option>';
            serviceSelect.disabled = !tenantId;
            hiddenStartTime.value = '';
            selectedTime = null;
            submitBtn.disabled = true;
            slotsContainer.innerHTML = 'Select a date to view slots.';

            if (tenantId) {
                const tenant = tenantsData.find(t => t.id == tenantId);
                if (tenant && tenant.services) {
                    tenant.services.forEach(service => {
                        const opt = document.createElement('option');
                        opt.value = service.id;
                        opt.textContent = `${service.name} (${service.duration_minutes} mins) - $${service.price}`;
                        serviceSelect.appendChild(opt);
                    });
                }
            }
        });

        // Fetch availability when date or tenant changes
        async function fetchAvailability() {
            const tenantId = tenantSelect.value;
            const date = dateInput.value;

            if (!tenantId || !date) return;

            slotsContainer.innerHTML = '<span class="text-gray-500">Loading slots...</span>';

            try {
                const response = await fetch(`/api/tenants/${tenantId}/availability?date=${date}`);
                const data = await response.json();

                if (!data.is_working_day) {
                    slotsContainer.innerHTML = `<span class="text-red-500 font-semibold col-span-3 text-center">${data.message}</span>`;
                    return;
                }

                slotsContainer.innerHTML = '';
                data.slots.forEach(slot => {
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.textContent = slot.time;
                    btn.className = `p-2.5 rounded-lg text-sm font-semibold border transition text-center `;
                    
                    if (!slot.available) {
                        btn.className += 'bg-red-50 text-red-400 border-red-200 cursor-not-allowed line-through';
                        btn.disabled = true;
                    } else {
                        btn.className += 'bg-white text-gray-700 border-gray-300 hover:bg-blue-50 hover:border-blue-500 hover:text-blue-600';
                        btn.addEventListener('click', () => {
                            document.querySelectorAll('#slotsContainer button').forEach(b => b.classList.remove('bg-blue-600', 'text-white', 'border-blue-600'));
                            btn.className = 'p-2.5 rounded-lg text-sm font-semibold border transition text-center bg-blue-600 text-white border-blue-600 shadow';
                            selectedTime = slot.datetime;
                            hiddenStartTime.value = slot.datetime;
                            submitBtn.disabled = false;
                        });
                    }
                    slotsContainer.appendChild(btn);
                });
            } catch (err) {
                slotsContainer.innerHTML = '<span class="text-red-500 col-span-3 text-center">Failed to load availability.</span>';
            }
        }

        dateInput.addEventListener('change', fetchAvailability);

        // Handle Form Submission
        document.getElementById('bookingForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            submitBtn.disabled = true;
            submitBtn.innerText = 'Processing...';

            const payload = {
                service_id: serviceSelect.value,
                start_time: hiddenStartTime.value
            };

            try {
                const response = await fetch('/api/bookings', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'Authorization': 'Bearer {{ $token }}'
                    },
                    body: JSON.stringify(payload)
                });

                const data = await response.json();

                if (response.ok) {
                    alertBox.className = 'mb-6 p-4 rounded-lg text-sm font-medium bg-green-50 text-green-800 border border-green-200 block';
                    alertBox.innerText = 'Success! Booking confirmed and confirmation email queued.';
                    this.reset();
                    slotsContainer.innerHTML = 'Select a barbershop and date to view slots.';
                    serviceSelect.disabled = true;
                } else {
                    alertBox.className = 'mb-6 p-4 rounded-lg text-sm font-medium bg-red-50 text-red-800 border border-red-200 block';
                    alertBox.innerText = data.error || data.message;
                }
            } catch (error) {
                alertBox.className = 'mb-6 p-4 rounded-lg text-sm font-medium bg-red-50 text-red-800 border border-red-200 block';
                alertBox.innerText = 'A network error occurred.';
            } finally {
                submitBtn.disabled = false;
                submitBtn.innerText = 'Confirm Appointment';
            }
        });
    </script>
</body>
</html>
