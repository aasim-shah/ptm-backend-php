<html>

<head>
    <title>Schools</title>
</head>

<body>

<table>

    <thead>
    <tr>
        <th>Name</th>
        <th>Gender</th>
        <th>School</th>
        <th>Email</th>
        <th>Phone</th>
        <th>DOB</th>

    </tr>
    </thead>

    <tbody>
    @foreach($principals as $data)
    <tr>
        <td>{{$data['first_name']}} {{$data['last_name']}}</td>
        <td>{{$data['gender']}}</td>
        <td>{{$data['school_name']}}</td>
        <td>{{$data['email']}}</td>
        <td>{{$data['mobile']}}</td>
        <td>{{$data['dob']}}</td>
    </tr>
    @endforeach
    </tbody>

</table>

</body>

</html>