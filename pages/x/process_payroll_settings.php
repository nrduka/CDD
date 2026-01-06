<!-- DATE INPUTS-->
<style>
    /* Default light gray color when the input is empty */
    input[type="date"] {
        color: lightgray;
    }

    /* Ensure the text and icon are gray before input */
    input[type="date"]::-webkit-datetime-edit,
    input[type="date"]::-webkit-datetime-edit-text,
    input[type="date"]::-webkit-datetime-edit-month-field,
    input[type="date"]::-webkit-datetime-edit-day-field,
    input[type="date"]::-webkit-datetime-edit-year-field {
        color: lightgray;
    }

    /* Remove border outline when focused */
    input[type="date"]:focus {
        outline: none;
    }

    /* Change to default color (black) when input has a value */
    input[type="date"].filled {
        color: black;
    }
    input[type="date"].filled::-webkit-datetime-edit,
    input[type="date"].filled::-webkit-datetime-edit-text,
    input[type="date"].filled::-webkit-datetime-edit-month-field,
    input[type="date"].filled::-webkit-datetime-edit-day-field,
    input[type="date"].filled::-webkit-datetime-edit-year-field {
        color: black;
    }
</style>

<script type="text/javascript">
    

    document.addEventListener("DOMContentLoaded", function() {
    const dateInput = document.getElementById('startDate');

    // Function to toggle the 'filled' class based on input value
    const toggleFilledClass = () => {
        if (dateInput.value) {
            dateInput.classList.add('filled');  // When there's a value, set the text color to black
        } else {
            dateInput.classList.remove('filled');  // If no value, show light gray (placeholder)
        }
    };

    // Call on page load in case there's a pre-filled value
    toggleFilledClass();

    // Add event listener to check when input changes
    dateInput.addEventListener('input', toggleFilledClass);
});
</script>

<script type="text/javascript">
    

    document.addEventListener("DOMContentLoaded", function() {
    const dateInput = document.getElementById('endDate');

    // Function to toggle the 'filled' class based on input value
    const toggleFilledClass = () => {
        if (dateInput.value) {
            dateInput.classList.add('filled');  // When there's a value, set the text color to black
        } else {
            dateInput.classList.remove('filled');  // If no value, show light gray (placeholder)
        }
    };

    // Call on page load in case there's a pre-filled value
    toggleFilledClass();

    // Add event listener to check when input changes
    dateInput.addEventListener('input', toggleFilledClass);
});
</script>

<script type="text/javascript">
    

    document.addEventListener("DOMContentLoaded", function() {
    const dateInput = document.getElementById('payPeriod');

    // Function to toggle the 'filled' class based on input value
    const toggleFilledClass = () => {
        if (dateInput.value) {
            dateInput.classList.add('filled');  // When there's a value, set the text color to black
        } else {
            dateInput.classList.remove('filled');  // If no value, show light gray (placeholder)
        }
    };

    // Call on page load in case there's a pre-filled value
    toggleFilledClass();

    // Add event listener to check when input changes
    dateInput.addEventListener('input', toggleFilledClass);
});
</script>





<!-- SELECT INPUTS -->
<style>
    /* Default placeholder color for the select */
    select.form-control {
        color: #ADADAE; /* Use Bootstrap's gray color for the placeholder */
    }

    /* When an actual option is selected, change the color to black */
    select.form-control.filled {
        color: black; /* Change text color to black when an option is selected */
    }


</style>

<script type="text/javascript">  
document.addEventListener("DOMContentLoaded", function() {
    const selectElements = document.querySelectorAll('select.form-control');

    selectElements.forEach(select => {
        // Function to check if the select has a value
        const toggleFilledClass = () => {
            if (select.value) {
                select.classList.add('filled');
            } else {
                select.classList.remove('filled');
            }
        };

        // Call on page load in case there's a pre-filled value
        toggleFilledClass();

        // Add event listener to check when selection changes
        select.addEventListener('change', toggleFilledClass);
    });
});

</script>

<!-- SELECT INPUTS ARROW -->
<style>
    /* Custom styles for select input with arrow */
    .custom-select-arrow {
        appearance: none;
        -webkit-appearance: none; /* Chrome, Safari, Opera */
        -moz-appearance: none; /* Firefox */
        background-image: url('data:image/svg+xml;charset=UTF-8,%3Csvg xmlns%3D%22http%3A//www.w3.org/2000/svg%22 width%3D%2224%22 height%3D%2224%22 viewBox%3D%220 0 24 24%22 fill%3D%22none%22 stroke%3D%22%23000%22 stroke-width%3D%222%22 stroke-linecap%3D%22round%22 stroke-linejoin%3D%22round%22 class%3D%22feather feather-chevron-down%22%3E%3Cpolyline points%3D%226 9 12 15 18 9%22/%3E%3C/svg%3E');
        background-repeat: no-repeat;
        background-position: right 10px center;
        background-size: 12px;
        padding-right: 30px; /* space for the arrow */
    }

    /* Optional: Adjusting styling to ensure cross-browser consistency */
    .custom-select-wrapper {
        position: relative;
        display: inline-block;
        width: 100%;
    }

    .custom-select-arrow:focus {
        outline: none;
    }
</style>


<!--PREVENT SUBMISSION FOR INVALID -->
<script>
    document.addEventListener("DOMContentLoaded", function () {
        const form = document.getElementById('userForm');
        const inputs = form.querySelectorAll('input, select');

        form.addEventListener('submit', function (event) {
            let isValid = true;
            inputs.forEach(function (input) {
                const label = form.querySelector(`label[for="${input.id}"]`);
                if (!input.checkValidity()) {
                    input.classList.add('is-invalid');
                    if (label) {
                        label.classList.add('text-danger');
                    }
                    isValid = false;
                } else {
                    input.classList.remove('is-invalid');
                    if (label) {
                        label.classList.remove('text-danger');
                    }
                }
            });

            // Prevent form submission if any field is invalid
            if (!isValid) {
                event.preventDefault();
            }
        });
    });
</script>

<!-- CONTAINER REMOVE DROP SHADOW -->
<style>
    .card {
       
        box-shadow: none !important;
    }
    .card-header, .card-body, .card-footer {
 
        box-shadow: none !important;
    }
</style>


<!-- SCROLLBAR -->
<style>
/* Custom Scrollbar Styling */
::-webkit-scrollbar {
    height: 10px; /* Make scrollbar bigger */
    background-color: #f0f0f0; /* Background of the scrollbar */
    border-radius: 10px;
}

::-webkit-scrollbar-thumb {
    background-color: #888; /* Default thumb color */
    border-radius: 10px;
    border: 1px solid #f0f0f0; /* Border around the thumb */
}

/* Change thumb color on hover */
::-webkit-scrollbar-thumb:hover {
    background-color: #555; /* Change color only on thumb hover */
}

/* Change thumb color when hovering over the table */
.dataTables_wrapper:hover ::-webkit-scrollbar-thumb {
    background-color: #888; /* Change thumb color to red when hovering over the table */
}

/* Ensure hover effect applies to the thumb when hovering over it */
.dataTables_wrapper .dataTables_scrollBody:hover::-webkit-scrollbar-thumb:hover {
    background-color: #555; /* Change thumb color to black when hovering over the thumb itself */
}

/* Add padding to make scroll more obvious */
.dataTables_wrapper .dataTables_scrollBody {
    padding-bottom: 1vw; /* Increases space, making scroll more noticeable */
    overflow-x: scroll; /* Ensures horizontal scrolling */
    margin-bottom: 1vw;
}
</style>
