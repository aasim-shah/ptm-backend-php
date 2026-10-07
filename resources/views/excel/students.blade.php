<html>

<head>
    <title>Students</title>
</head>

<body>

<table>

    <thead>
    <tr>
        <th>Full Name</th>
        <th>Class</th>
        <th>Email</th>
        <th>Contact Number</th>
        <th>Gender</th>
        <th>Father Name</th>
        <th>Father Mobile</th>
        <th>Mother Name</th>
        <th>Mother Mobile</th>
        <th>Date Of Birth</th>
        <th>Current Address</th>
        <th>Permanent Address</th>
        <th>Language</th>

    </tr>
    </thead>

    <tbody>
    @foreach($students as $data)
    <tr>
        <td>{{$data['first_name']}} {{$data['last_name']}}</td>
        <td>{{$data['class_section_name']}}</td>
        <td>{{$data['email']}}</td>
        <td>{{$data['mobile']}}</td>
        <td>{{$data['gender']}}</td>
        <td>{{$data['father_first_name'] ?? ''}} {{$data['father_last_name'] ?? ''}}</td>
        <td>{{$data['father_mobile'] ?? ''}}</td>
        <td>{{$data['mother_first_name'] ?? ''}} {{$data['mother_last_name'] ?? ''}}</td>
        <td>{{$data['mother_mobile'] ?? ''}}</td>
        <td>{{$data['dob']}}</td>
        <td>{{$data['current_address']}}</td>
        <td>{{$data['permanent_address']}}</td>
        <td>{{$data['language'] ?? ''}}</td>
    </tr>
    @endforeach
    </tbody>

</table>

</body>

</html>