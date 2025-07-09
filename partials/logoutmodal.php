<!-- partials/logout_modal.php -->

<!-- Logout Modal -->
<div id="logoutModal"
    class="hidden fixed inset-0 bg-black bg-opacity-30 backdrop-blur-sm flex items-center justify-center z-50">
    <div class="bg-white p-6 rounded shadow-lg w-full max-w-sm">
        <h2 class="text-xl font-semibold mb-4">Confirm Logout</h2>
        <p class="mb-6 text-gray-700">Are you sure you want to logout?</p>
        <div class="flex justify-end space-x-4">
            <button onclick="hideLogoutModal()" class="px-4 py-2 bg-gray-300 hover:bg-gray-400 rounded">Cancel</button>
            <form method="GET" action="partials/logout.php">
                <input type="hidden" name="confirm" value="yes">
                <button type="submit" class="px-4 py-2 bg-red-600 text-white hover:bg-red-700 rounded">Yes,
                    Logout</button>
            </form>
        </div>
    </div>
</div>

<script>
    function showLogoutModal() {
        document.getElementById('logoutModal').classList.remove('hidden');
    }

    function hideLogoutModal() {
        document.getElementById('logoutModal').classList.add('hidden');
    }
</script>