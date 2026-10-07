<html>

<head>
    <title>Parents</title>
</head>

<body>

<table>

    <thead>
    <tr>
        <th>Full Name</th>
        <th>Email</th>
        <th>Contact Number</th>
        <th>Gender</th>
        <th>Date Of Birth</th>
        <th>Current Address</th>
        <th>Permanent Address</th>
        <th>Language</th>

    </tr>
    </thead>

    <tbody>
    @foreach($parents as $data)
    <tr>
        <td>{{$data->title}} {{$data->first_name}} {{$data->last_name}}</td>
        <td>{{$data->email}}</td>
        <td>{{$data->mobile}}</td>
        <td>{{$data->gender}}</td>
        <td>{{$data->dob}}</td>
        <td>{{$data->current_address}}</td>
        <td>{{$data->permanent_address}}</td>
        <td>{{$data->language}}</td>
    </tr>
    @endforeach
    </tbody>

</table>

</body>

</html>