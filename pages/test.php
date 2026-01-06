<div class="card-header d-sm-flex d-block">
                                            
                                                <ul class="nav nav-tabs nav-tabs-header mb-0 d-sm-flex d-block" role="tablist">
                                                    <li class="nav-item m-1">
                                                        <a class="nav-link active" data-bs-toggle="tab" role="tab" aria-current="page"
                                                        href="#spouse-info" aria-selected="true">Spouse Information</a>
                                                    </li>
                                                    <li class="nav-item m-1">
                                                        <a class="nav-link" data-bs-toggle="tab" role="tab" aria-current="page"
                                                        href="#father-info" aria-selected="true">Father's Information</a>
                                                    </li>
                                                    <li class="nav-item m-1">
                                                        <a class="nav-link" data-bs-toggle="tab" role="tab" aria-current="page"
                                                        href="#mother-info" aria-selected="true">Mother's Information</a>
                                                    </li>
                                                </ul>
                                            </div>








                                            s
                                            <div class="card-body">
                                                <div class="tab-content">
                                                                                                      
                                                        
                                                        <div class="tab-pane show active" id="spouse-info"
                                                            role="tabpanel">
                                                        <div class="p-sm-3 p-0">    
                                                            <div class="col-xl-6 row gy-4 mb-4">
                                                                <div class="col-xl-12 gap-4">
                                                                    <label for="spouse_name" class="form-label">Full Name</label>
                                                                    <input type="text" class="form-control" name="spouse_name" placeholder="Name" value="<?php echo htmlspecialchars($gen_info['spouse_name']); ?>">
                                                                </div>
                                                                <div class="col-xl-12 gap-4">
                                                                    <label for="spouse_occupation" class="form-label">Occupation</label>
                                                                    <input type="text" class="form-control" name="spouse_occupation" placeholder="Name" value="<?php echo htmlspecialchars($gen_info['spouse_occupation']); ?>">
                                                                </div>
                                                                <div class="col-xl-12 gap-4">
                                                                    <label for="spouse_dob" class="form-label">Date of Birth</label>
                                                                    <input type="date" class="form-control" id="spouse_dob" name="spouse_dob" value="<?php echo htmlspecialchars($gen_info['spouse_dob']); ?>">
                                                                    <div class="invalid-feedback">
                                                                        Please Provide Date of Birth.
                                                                    </div>
                                                                </div>
                                                                <div class="col-xl-12 gap-4">
                                                                    <label for="spouse_address" class="form-label">Address</label>
                                                                    <input type="text" class="form-control" id="spouse_address" name="spouse_address" placeholder="Address" value="<?php echo htmlspecialchars($gen_info['spouse_address']); ?>">
                                                                    <div class="invalid-feedback">
                                                                        Please Provide Address.
                                                                    </div> 
                                                                </div>
                                                        
                                                                <div class="col-xl-12 gap-4">
                                                                    <label for="spouse_contact" class="form-label">Contact No.</label>
                                                                    <input type="text" class="form-control" id="spouse_contact" name="spouse_contact" placeholder="Contact Number" value="<?php echo htmlspecialchars($gen_info['spouse_contact']); ?>">
                                                                    <div class="invalid-feedback">
                                                                        Please Provide Contact Number.
                                                                    </div>
                                                                </div>
                                                                    <div class="col-xl-12 gap-4">
                                                                        <label for="spouse_dom" class="form-label">Date of Marriage</label>
                                                                        <input type="date" class="form-control" id="spouse_dom" name="spouse_dom" value="<?php echo htmlspecialchars($gen_info['spouse_dom']); ?>">
                                                                        <div class="invalid-feedback">
                                                                            Please Provide Date of Birth.
                                                                    </div>
                                                                </div>
                                                                <div class="col-xl-12 gap-4">
                                                                    <label for="spouse_tin" class="form-label">TIN #</label>
                                                                    <input type="text" class="form-control" id="spouse_tin" name="spouse_tin" placeholder="TIN Number" value="<?php echo htmlspecialchars($gen_info['spouse_tin']); ?>">
                                                                    <div class="invalid-feedback">
                                                                        Please Provide Address.
                                                                    </div> 
                                                                </div>
                                        
                                                            </div>
                                                            
                                            
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Father's Information -->

                                                <div class="tab-content">
                                                    <div class="tab-pane show" id="father-info"
                                                            role="tabpanel">
                                                        <div class="p-sm-3 p-0">
                                                                
                                                            <div class="col-xl-6 row gy-4 mb-4">
                                                                <div class="col-xl-12 gap-4">
                                                                    <label for="father_name" class="form-label">Full Name</label>
                                                                    <input type="text" class="form-control" name="father_name" placeholder="Name" value="<?php echo htmlspecialchars($gen_info['father_name']); ?>">
                                                                </div>
                                                                <div class="col-xl-12 gap-4">
                                                                    <label for="father_occupaton" class="form-label">Occupation</label>
                                                                    <input type="text" class="form-control" name="father_occupation" placeholder="Name" value="<?php echo htmlspecialchars($gen_info['father_occupation']); ?>">
                                                                </div>
                                                                <div class="col-xl-12 gap-4">
                                                                    <label for="father_contact" class="form-label">Contact No.</label>
                                                                    <input type="text" class="form-control" id="father_contact" name="father_contact" placeholder="Contact Number" value="<?php echo htmlspecialchars($gen_info['father_contact']); ?>">
                                                                    <div class="invalid-feedback">
                                                                        Please Provide Contact Number.
                                                                    </div>
                                                                </div>
                                                                <div class="col-xl-12 gap-4">
                                                                    <label for="father_dob" class="form-label">Date of Birth</label>
                                                                    <input type="date" class="form-control" id="father_dob" name="father_dob" value="<?php echo htmlspecialchars($gen_info['father_dob']); ?>">
                                                                    <div class="invalid-feedback">
                                                                        Please Provide Date of Birth.
                                                                    </div>
                                                                </div>
                                                                <div class="col-xl-12 gap-4">
                                                                    <label for="father_address" class="form-label">Address</label>
                                                                    <input type="text" class="form-control" id="father_address" name="father_address" placeholder="Address" value="<?php echo htmlspecialchars($gen_info['father_address']); ?>">
                                                                    <div class="invalid-feedback">
                                                                        Please Provide Address.
                                                                    </div> 
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Mother's Information -->

                                                <div class="tab-content">
                                                    <div class="tab-pane" id="mother-info"
                                                            role="tabpanel">
                                                        <div class="p-sm-3 p-0">
                                                                
                                                            <div class="col-xl-6 row gy-4 mb-4">
                                                                <div class="col-xl-12 gap-4">
                                                                    <label for="mother_name" class="form-label">Full Name</label>
                                                                    <input type="text" class="form-control" name="mother_name" placeholder="Name" value="<?php echo htmlspecialchars($gen_info['mother_name']); ?>">
                                                                </div>
                                                                <div class="col-xl-12 gap-4">
                                                                    <label for="mother_occupaton" class="form-label">Occupation</label>
                                                                    <input type="text" class="form-control" name="mother_occupation" placeholder="Name" value="<?php echo htmlspecialchars($gen_info['mother_occupation']); ?>">
                                                                </div>
                                                                <div class="col-xl-12 gap-4">
                                                                    <label for="mother_contact" class="form-label">Contact No.</label>
                                                                    <input type="text" class="form-control" id="mother_contact" name="mother_contact" placeholder="Contact Number" value="<?php echo htmlspecialchars($gen_info['mother_contact']); ?>">
                                                                    <div class="invalid-feedback">
                                                                        Please Provide Contact Number.
                                                                    </div>
                                                                </div>
                                                                <div class="col-xl-12 gap-4">
                                                                    <label for="mother_dob" class="form-label">Date of Birth</label>
                                                                    <input type="date" class="form-control" id="mother_dob" name="mother_dob" value="<?php echo htmlspecialchars($gen_info['mother_dob']); ?>">
                                                                    <div class="invalid-feedback">
                                                                        Please Provide Date of Birth.
                                                                    </div>
                                                                </div>
                                                                <div class="col-xl-12 gap-4">
                                                                    <label for="mother_address" class="form-label">Address</label>
                                                                    <input type="text" class="form-control" id="mother_address" name="mother_address" placeholder="Address" value="<?php echo htmlspecialchars($gen_info['mother_address']); ?>">
                                                                    <div class="invalid-feedback">
                                                                        Please Provide Address.
                                                                    </div> 
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>


                                            </div>